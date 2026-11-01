<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpenseClaimController extends Controller
{
    private const CATEGORIES = ['meals', 'transport', 'accommodation', 'supplies', 'communications', 'other'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);

        $scope = $this->claimsFor($user);
        $claims = (clone $scope)
            ->with(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $claims->each(fn (ExpenseClaim $claim) => $claim->setAttribute(
            'can_review',
            $claim->status === 'pending'
                && (int) $claim->submitted_by !== (int) $user->id
                && PeopleScope::canReview($user, $claim->employee),
        ));
        $approvedAmounts = (clone $scope)
            ->where('status', 'approved')
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->get();
        $total = (clone $scope)->count();

        return response()->json([
            'claims' => $claims,
            'meta' => [
                'total' => (clone $scope)->count(),
                'pending' => (clone $scope)->where('status', 'pending')->count(),
                'approved' => (clone $scope)->where('status', 'approved')->count(),
                'rejected' => (clone $scope)->where('status', 'rejected')->count(),
                'approved_amounts' => $approvedAmounts,
                'claim_limit' => 300,
                'truncated' => $total > 300,
            ],
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $employee = PeopleScope::employees($user)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        $data = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'merchant' => ['required', 'string', 'max:140'],
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'description' => ['required', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $receiptPath = null;
        $receipt = $request->file('receipt');
        if ($receipt) {
            $receiptPath = $receipt->store('expense-receipts/'.$user->company_id, 'local');
            if (! $receiptPath) {
                abort(500, 'The receipt could not be stored. Please try again.');
            }
        }

        try {
            $claim = DB::transaction(function () use ($data, $employee, $receipt, $receiptPath, $user) {
                $claim = ExpenseClaim::create([
                    'company_id' => $employee->company_id,
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                    'submitted_by' => $user->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => trim($employee->first_name.' '.$employee->last_name),
                    'department' => $employee->department,
                    'category' => $data['category'],
                    'merchant' => trim($data['merchant']),
                    'expense_date' => $data['expense_date'],
                    'amount' => $data['amount'],
                    'currency' => $employee->currency ?: $user->company?->currency ?: 'USD',
                    'description' => trim($data['description']),
                    'receipt_path' => $receiptPath,
                    'receipt_original_name' => $receipt ? $this->safeOriginalName($receipt->getClientOriginalName()) : null,
                    'receipt_mime' => $receipt?->getMimeType(),
                    'receipt_size' => $receipt?->getSize(),
                    'status' => 'pending',
                ]);
                AuditTrail::record('expenses.claim_submitted', $claim, [
                    'employee_id' => $employee->id,
                    'amount' => $claim->amount,
                    'currency' => $claim->currency,
                ]);

                return $claim;
            });
        } catch (Throwable $exception) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }

            throw $exception;
        }

        return response()->json(['claim' => $claim->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name'])], 201);
    }

    public function review(Request $request, ExpenseClaim $claim): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertClaimScope($user, $claim);
        abort_unless(PeopleScope::canReview($user, $claim->employee), 403, 'You may only review claims from your direct reports or authorized people scope.');
        abort_if((int) $claim->submitted_by === (int) $user->id, 403, 'You cannot review your own expense claim.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('decision') === 'reject')],
        ]);
        if ($data['decision'] === 'reject' && ! trim($data['review_note'] ?? '')) {
            throw ValidationException::withMessages([
                'review_note' => ['Add a reason so the employee understands why this claim was rejected.'],
            ]);
        }

        $claim = DB::transaction(function () use ($claim, $data, $user) {
            $lockedClaim = ExpenseClaim::query()->with('employee')->lockForUpdate()->findOrFail($claim->id);
            $this->assertClaimScope($user, $lockedClaim);
            abort_unless(PeopleScope::canReview($user, $lockedClaim->employee), 403);
            abort_if((int) $lockedClaim->submitted_by === (int) $user->id, 403, 'You cannot review your own expense claim.');
            abort_unless($lockedClaim->status === 'pending', 409, 'This claim has already been reviewed.');

            $lockedClaim->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $user->id,
                'review_note' => trim($data['review_note'] ?? '') ?: null,
                'reviewed_at' => now(),
            ]);
            AuditTrail::record('expenses.claim_'.$lockedClaim->status, $lockedClaim, [
                'employee_id' => $lockedClaim->employee_id,
                'review_note' => $lockedClaim->review_note,
            ]);

            return $lockedClaim;
        });

        return response()->json(['claim' => $claim->fresh()->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name'])]);
    }

    public function receipt(Request $request, ExpenseClaim $claim)
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertClaimScope($user, $claim);
        abort_unless($claim->receipt_path && Storage::disk('local')->exists($claim->receipt_path), 404);

        return Storage::disk('local')->download($claim->receipt_path, $claim->receipt_original_name, [
            'Content-Type' => $claim->receipt_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function claimsFor($user): Builder
    {
        return ExpenseClaim::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', PeopleScope::employees($user)->select('id'));
    }

    private function assertClaimScope($user, ExpenseClaim $claim): void
    {
        abort_unless((int) $claim->company_id === (int) $user->company_id, 404);
        abort_unless(PeopleScope::employees($user)->whereKey($claim->employee_id)->exists(), 404);
    }

    private function ensureAccess($user): void
    {
        abort_unless(in_array($user->role, ['employee', 'manager', 'hr', 'admin'], true), 403, 'Expense claims are not available for this role.');
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'receipt';

        return mb_substr($name, 0, 180);
    }
}
