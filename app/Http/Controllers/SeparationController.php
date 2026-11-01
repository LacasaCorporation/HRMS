<?php

namespace App\Http\Controllers;

use App\Models\LifecycleCase;
use App\Models\SeparationSettlement;
use App\Models\SeparationSettlementItem;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeparationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $settlements = $this->settlementsFor($user)
            ->with([
                'branch:id,name',
                'items',
                'offboardingCase.tasks',
                'creator:id,name',
                'submittedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->orderByDesc('id')
            ->limit(200)
            ->get();
        $offboardingCases = $this->offboardingCasesFor($user)
            ->with(['employee:id,branch_id,employee_code,first_name,last_name,department,status,currency', 'employee.branch:id,name', 'tasks'])
            ->orderByDesc('effective_date')
            ->get();

        return response()->json([
            'settlements' => $settlements,
            'offboarding_cases' => $offboardingCases,
            'meta' => [
                'settlement_count' => $settlements->count(),
                'draft_count' => $settlements->whereIn('status', ['draft', 'changes_requested'])->count(),
                'review_count' => $settlements->where('status', 'in_review')->count(),
                'approved_count' => $settlements->where('status', 'approved')->count(),
                'clearance_ready_count' => $offboardingCases->filter(fn (LifecycleCase $case) => $this->clearancesComplete($case))->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'lifecycle_case_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $case = $this->offboardingCasesFor($user)
            ->with('employee')
            ->whereKey($data['lifecycle_case_id'])
            ->firstOrFail();
        abort_if(SeparationSettlement::query()->where('lifecycle_case_id', $case->id)->exists(), 409, 'A settlement workpaper already exists for this offboarding case.');

        try {
            $settlement = SeparationSettlement::create([
                'company_id' => $user->company_id,
                'branch_id' => $case->branch_id,
                'lifecycle_case_id' => $case->id,
                'employee_id' => $case->employee_id,
                'created_by' => $user->id,
                'employee_code' => $case->employee->employee_code,
                'employee_name' => trim($case->employee->first_name.' '.$case->employee->last_name),
                'department' => $case->employee->department,
                'currency' => $case->employee->currency,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A settlement workpaper already exists for this offboarding case.');
        }
        AuditTrail::record('separation.settlement_created', $settlement, [
            'lifecycle_case_id' => $case->id,
            'employee_id' => $case->employee_id,
        ]);

        return response()->json(['settlement' => $settlement->load(['items', 'offboardingCase.tasks', 'branch:id,name'])], 201);
    }

    public function updateItems(Request $request, SeparationSettlement $settlement): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertSettlementScope($user, $settlement);
        abort_unless(in_array($settlement->status, ['draft', 'changes_requested'], true), 409, 'Submitted settlement items are locked.');
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.category' => ['required', Rule::in(['earning', 'recovery'])],
            'items.*.description' => ['required', 'string', 'max:180'],
            'items.*.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $categoryTotals = ['earning' => 0, 'recovery' => 0];
        foreach ($data['items'] as $line) {
            $categoryTotals[$line['category']] += $this->toCents($line['amount']);
        }
        if (max($categoryTotals) > 999999999999) {
            throw ValidationException::withMessages([
                'items' => ['Each category total must be no more than 9,999,999,999.99.'],
            ]);
        }

        DB::transaction(function () use ($data, $settlement) {
            $settlement->items()->delete();
            foreach ($data['items'] as $line) {
                $settlement->items()->create($line);
            }
            AuditTrail::record('separation.settlement_items_updated', $settlement, [
                'item_count' => count($data['items']),
            ]);
        });

        return response()->json(['settlement' => $settlement->fresh()->load(['items', 'offboardingCase.tasks'])]);
    }

    public function submit(Request $request, SeparationSettlement $settlement): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertSettlementScope($user, $settlement);
        abort_unless(in_array($settlement->status, ['draft', 'changes_requested'], true), 409, 'Only draft or returned workpapers can be submitted.');
        abort_if(!$settlement->items()->exists(), 422, 'Add and save manual settlement workpaper lines before submitting.');
        $case = $settlement->offboardingCase()->with('tasks')->firstOrFail();
        abort_if(!$this->clearancesComplete($case), 422, 'Complete and close the offboarding checklist before submitting the settlement workpaper.');

        $settlement->update([
            'status' => 'in_review',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'review_note' => null,
        ]);
        AuditTrail::record('separation.settlement_submitted', $settlement, ['item_count' => $settlement->items()->count()]);

        return response()->json(['settlement' => $settlement->fresh()->load(['items', 'offboardingCase.tasks', 'submittedBy:id,name'])]);
    }

    public function review(Request $request, SeparationSettlement $settlement): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertSettlementScope($user, $settlement);
        abort_unless($settlement->status === 'in_review', 409, 'Only settlement workpapers in review can be approved or returned.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('decision') === 'return')],
        ]);
        $settlement->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'changes_requested',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);
        AuditTrail::record('separation.settlement_reviewed', $settlement, ['decision' => $data['decision']]);

        return response()->json(['settlement' => $settlement->fresh()->load([
            'items',
            'offboardingCase.tasks',
            'reviewedBy:id,name',
            'submittedBy:id,name',
        ])]);
    }

    private function settlementsFor($user): Builder
    {
        return SeparationSettlement::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function offboardingCasesFor($user): Builder
    {
        return LifecycleCase::query()
            ->where('company_id', $user->company_id)
            ->where('type', 'offboarding')
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function assertSettlementScope($user, SeparationSettlement $settlement): void
    {
        abort_unless((int) $settlement->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $settlement->branch_id !== (int) $user->branch_id, 404);
    }

    private function clearancesComplete(LifecycleCase $case): bool
    {
        return $case->type === 'offboarding'
            && $case->status === 'completed'
            && $case->tasks->every(fn ($task) => $task->status === 'completed');
    }

    private function toCents(string|int|float $amount): int
    {
        $normalized = is_float($amount)
            ? json_encode($amount, JSON_PRESERVE_ZERO_FRACTION)
            : (string) $amount;
        [$whole, $fraction] = array_pad(explode('.', (string) $normalized, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function ensureAccess($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Separation records are restricted to HR and administrators.');
    }
}
