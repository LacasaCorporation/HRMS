<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollCycle;
use App\Models\PayrollItem;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePayrollAccess($user);

        $cycles = PayrollCycle::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->with([
                'branch:id,name',
                'items',
                'createdBy:id,name',
                'submittedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $employees = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'department', 'currency']);

        $branches = $user->company->branches()
            ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'cycles' => $cycles,
            'employees' => $employees,
            'branches' => $branches,
            'meta' => [
                'cycle_count' => $cycles->count(),
                'draft_count' => $cycles->where('status', 'draft')->count(),
                'review_count' => $cycles->where('status', 'in_review')->count(),
                'approved_count' => $cycles->where('status', 'approved')->count(),
            ],
        ]);
    }

    public function storeCycle(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePayrollAccess($user);
        $data = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'pay_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['AED', 'INR', 'SAR'])],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $user->company_id)],
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $requestedBranchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($user->branch_id && $requestedBranchId !== null) {
            $this->assertBranchScope($user, $requestedBranchId);
        }
        $branchId = $user->branch_id ?: $requestedBranchId;
        $duplicateCycle = PayrollCycle::query()
            ->where('company_id', $user->company_id)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId), fn (Builder $query) => $query->whereNull('branch_id'))
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->where('currency', $data['currency'])
            ->exists();
        abort_if($duplicateCycle, 409, 'A payroll cycle already exists for this period, branch, and currency.');

        $employees = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->where('currency', $data['currency'])
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->whereIn('id', $data['employee_ids'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        if ($employees->count() !== count($data['employee_ids'])) {
            throw ValidationException::withMessages([
                'employee_ids' => ['Select active employees in your company and branch scope with the chosen currency.'],
            ]);
        }

        try {
            $cycle = DB::transaction(function () use ($data, $user, $branchId, $employees) {
                $cycle = PayrollCycle::create([
                    'company_id' => $user->company_id,
                    'branch_id' => $branchId,
                    'branch_scope_key' => $branchId ?? 0,
                    'created_by' => $user->id,
                    'period_start' => $data['period_start'],
                    'period_end' => $data['period_end'],
                    'pay_date' => $data['pay_date'] ?? null,
                    'currency' => $data['currency'],
                    'status' => 'draft',
                ]);

                foreach ($employees as $employee) {
                    $cycle->items()->create([
                        'employee_id' => $employee->id,
                        'employee_code' => $employee->employee_code,
                        'employee_name' => trim($employee->first_name.' '.$employee->last_name),
                        'department' => $employee->department,
                    ]);
                }

                AuditTrail::record('payroll.cycle_created', $cycle, [
                    'period_start' => $cycle->period_start->toDateString(),
                    'period_end' => $cycle->period_end->toDateString(),
                    'currency' => $cycle->currency,
                    'employee_count' => $employees->count(),
                ]);

                return $cycle;
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'A payroll cycle already exists for this period, branch, and currency.');
        }

        return response()->json([
            'cycle' => $cycle->load(['branch:id,name', 'items', 'createdBy:id,name']),
        ], 201);
    }

    public function updateItem(Request $request, PayrollItem $item): JsonResponse
    {
        $user = $request->user();
        $this->ensurePayrollAccess($user);
        $cycle = $item->cycle;
        $this->assertCycleScope($user, $cycle);
        abort_unless(in_array($cycle->status, ['draft', 'changes_requested'], true), 409, 'Payroll inputs are locked after submission.');

        $data = $request->validate([
            'base_pay' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'other_earnings' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'manual_deductions' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $grossCents = $this->toCents($data['base_pay']) + $this->toCents($data['other_earnings']);
        $deductionCents = $this->toCents($data['manual_deductions']);
        if ($deductionCents > $grossCents) {
            throw ValidationException::withMessages([
                'manual_deductions' => ['Manual deductions cannot exceed base pay plus other earnings.'],
            ]);
        }
        if ($grossCents > 999999999999) {
            throw ValidationException::withMessages([
                'other_earnings' => ['Base pay plus other earnings cannot exceed 9,999,999,999.99.'],
            ]);
        }

        $item->update([
            'base_pay' => $data['base_pay'],
            'other_earnings' => $data['other_earnings'],
            'manual_deductions' => $data['manual_deductions'],
            'gross_pay' => $this->fromCents($grossCents),
            'net_pay' => $this->fromCents($grossCents - $deductionCents),
            'notes' => $data['notes'] ?? null,
            'updated_by' => $user->id,
        ]);
        AuditTrail::record('payroll.item_updated', $item, ['payroll_cycle_id' => $cycle->id]);

        return response()->json(['item' => $item->fresh()]);
    }

    public function submitCycle(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $user = $request->user();
        $this->ensurePayrollAccess($user);
        $this->assertCycleScope($user, $cycle);
        abort_unless(in_array($cycle->status, ['draft', 'changes_requested'], true), 409, 'Only draft or returned payroll cycles can be submitted.');
        abort_if($cycle->items()->count() === 0, 422, 'Add at least one employee before submitting the payroll cycle.');
        abort_if(
            $cycle->items()->whereNull('net_pay')->exists(),
            422,
            'Enter and save all employee pay inputs before submitting this cycle for review.',
        );

        $cycle->update([
            'status' => 'in_review',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'review_note' => null,
        ]);
        AuditTrail::record('payroll.cycle_submitted', $cycle, ['item_count' => $cycle->items()->count()]);

        return response()->json(['cycle' => $cycle->fresh()->load(['branch:id,name', 'items', 'createdBy:id,name', 'submittedBy:id,name', 'reviewedBy:id,name'])]);
    }

    public function reviewCycle(Request $request, PayrollCycle $cycle): JsonResponse
    {
        $user = $request->user();
        $this->ensurePayrollAccess($user);
        $this->assertCycleScope($user, $cycle);
        abort_unless($cycle->status === 'in_review', 409, 'Only payroll cycles in review can be approved or returned.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('decision') === 'return')],
        ]);

        $cycle->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'changes_requested',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);
        AuditTrail::record('payroll.cycle_reviewed', $cycle, [
            'decision' => $data['decision'],
            'review_note' => $data['review_note'] ?? null,
        ]);

        return response()->json(['cycle' => $cycle->fresh()->load(['branch:id,name', 'items', 'createdBy:id,name', 'submittedBy:id,name', 'reviewedBy:id,name'])]);
    }

    private function ensurePayrollAccess($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Payroll records are restricted to HR and administrators.');
    }

    private function assertBranchScope($user, ?int $branchId): void
    {
        abort_if($user->branch_id && (int) $user->branch_id !== (int) $branchId, 403, 'This branch is outside your access scope.');
    }

    private function assertCycleScope($user, PayrollCycle $cycle): void
    {
        abort_unless((int) $cycle->company_id === (int) $user->company_id, 404);
        $this->assertBranchScope($user, $cycle->branch_id);
    }

    private function toCents(string|int|float $amount): int
    {
        $normalized = is_float($amount)
            ? json_encode($amount, JSON_PRESERVE_ZERO_FRACTION)
            : (string) $amount;
        [$whole, $fraction] = array_pad(explode('.', (string) $normalized, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
