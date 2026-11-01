<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LoanAdvance;
use App\Models\LoanAdvanceInstallment;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoanAdvanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $scope = $this->loansFor($user);
        $loans = (clone $scope)
            ->with(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'installments.repayments.recorder:id,name'])
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $loans->each(fn (LoanAdvance $loan) => $this->decorateLoan($loan, $user));
        $total = (clone $scope)->count();

        $employee = PeopleScope::employees($user)->where('user_id', $user->id)->first();
        $canApply = $employee && $employee->status === 'active'
            && ! LoanAdvance::query()->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])->exists();

        return response()->json([
            'loans' => $loans,
            'meta' => [
                'total' => $total,
                'pending' => (clone $scope)->where('status', 'pending')->count(),
                'approved' => (clone $scope)->where('status', 'approved')->count(),
                'rejected' => (clone $scope)->where('status', 'rejected')->count(),
                'repaid' => (clone $scope)->where('status', 'repaid')->count(),
                'limit' => 300,
                'truncated' => $total > 300,
                'can_apply' => (bool) $canApply,
            ],
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
            'requested_amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'requested_installments' => ['required', 'integer', 'min:1', 'max:60'],
            'purpose' => ['required', 'string', 'max:2000'],
        ]);

        $loan = DB::transaction(function () use ($data, $employee, $user) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            abort_if(
                LoanAdvance::query()->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])->exists(),
                409,
                'You already have a pending request or an active approved loan.',
            );
            $loan = LoanAdvance::create([
                'company_id' => $employee->company_id,
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'submitted_by' => $user->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => trim($employee->first_name.' '.$employee->last_name),
                'department' => $employee->department,
                'currency' => $employee->currency ?: $user->company?->currency ?: 'USD',
                'requested_amount' => $data['requested_amount'],
                'requested_installments' => $data['requested_installments'],
                'purpose' => trim($data['purpose']),
                'status' => 'pending',
            ]);
            AuditTrail::record('loans.advance_submitted', $loan, [
                'employee_id' => $employee->id,
                'requested_amount' => $loan->requested_amount,
                'currency' => $loan->currency,
            ]);

            return $loan;
        });

        return response()->json(['loan' => $this->decorateLoan(
            $loan->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'installments.repayments.recorder:id,name']),
            $user,
        )], 201);
    }

    public function review(Request $request, LoanAdvance $loan): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertLoanScope($user, $loan);
        abort_unless(PeopleScope::canReview($user, $loan->employee), 403, 'You may only review requests from your direct reports or authorized people scope.');
        abort_if((int) $loan->submitted_by === (int) $user->id, 403, 'You cannot review your own loan request.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'approved_amount' => ['required_if:decision,approve', 'nullable', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'repayment_installments' => ['required_if:decision,approve', 'nullable', 'integer', 'min:1', 'max:60'],
            'first_due_date' => ['required_if:decision,approve', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('decision') === 'reject')],
        ]);

        if ($data['decision'] === 'reject' && ! trim($data['review_note'] ?? '')) {
            throw ValidationException::withMessages([
                'review_note' => ['Add a reason so the employee understands why this request was rejected.'],
            ]);
        }
        if ($data['decision'] === 'approve' && $this->toCents($data['approved_amount']) > $this->toCents($loan->requested_amount)) {
            throw ValidationException::withMessages([
                'approved_amount' => ['The approved amount cannot exceed the amount requested.'],
            ]);
        }
        if ($data['decision'] === 'approve' && (int) $data['repayment_installments'] > $this->toCents($data['approved_amount'])) {
            throw ValidationException::withMessages([
                'repayment_installments' => ['The installment count cannot exceed the number of cents in the approved amount.'],
            ]);
        }

        $loan = DB::transaction(function () use ($data, $loan, $user) {
            $lockedLoan = LoanAdvance::query()->with('employee')->lockForUpdate()->findOrFail($loan->id);
            $this->assertLoanScope($user, $lockedLoan);
            abort_unless(PeopleScope::canReview($user, $lockedLoan->employee), 403);
            abort_if((int) $lockedLoan->submitted_by === (int) $user->id, 403, 'You cannot review your own loan request.');
            abort_unless($lockedLoan->status === 'pending', 409, 'This loan request has already been reviewed.');

            if ($data['decision'] === 'approve') {
                $approvedCents = $this->toCents($data['approved_amount']);
                abort_if($approvedCents > $this->toCents($lockedLoan->requested_amount), 422, 'The approved amount cannot exceed the amount requested.');
                $count = (int) $data['repayment_installments'];
                if ($count > $approvedCents) {
                    throw ValidationException::withMessages([
                        'repayment_installments' => ['The installment count cannot exceed the number of cents in the approved amount.'],
                    ]);
                }
                $lockedLoan->update([
                    'status' => 'approved',
                    'approved_amount' => $data['approved_amount'],
                    'repayment_installments' => $count,
                    'first_due_date' => $data['first_due_date'],
                    'review_note' => trim($data['review_note'] ?? '') ?: null,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
                $baseAmount = intdiv($approvedCents, $count);
                $extraCents = $approvedCents % $count;
                $firstDueDate = Carbon::createFromFormat('Y-m-d', $data['first_due_date']);
                for ($sequence = 1; $sequence <= $count; $sequence++) {
                    $installmentCents = $baseAmount + ($sequence <= $extraCents ? 1 : 0);
                    $lockedLoan->installments()->create([
                        'sequence' => $sequence,
                        'due_date' => $firstDueDate->copy()->addMonthsNoOverflow($sequence - 1)->toDateString(),
                        'amount' => $this->fromCents($installmentCents),
                    ]);
                }
            } else {
                $lockedLoan->update([
                    'status' => 'rejected',
                    'review_note' => trim($data['review_note']),
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }

            AuditTrail::record('loans.advance_'.$lockedLoan->status, $lockedLoan, [
                'employee_id' => $lockedLoan->employee_id,
                'approved_amount' => $lockedLoan->approved_amount,
                'repayment_installments' => $lockedLoan->repayment_installments,
                'review_note' => $lockedLoan->review_note,
            ]);

            return $lockedLoan;
        });

        return response()->json(['loan' => $this->decorateLoan(
            $loan->fresh()->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'installments.repayments.recorder:id,name']),
            $user,
        )]);
    }

    public function recordRepayment(Request $request, LoanAdvanceInstallment $installment): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canManagePeople(), 403, 'Only HR or administrators may record repayment reconciliation.');
        $installment->load('loanAdvance.employee');
        $loan = $installment->loanAdvance;
        $this->assertLoanScope($user, $loan);
        abort_unless($loan->status === 'approved', 409, 'Only approved loans can have repayments reconciled.');

        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'recorded_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($data, $installment, $user, $loan) {
            $lockedLoan = LoanAdvance::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
            $this->assertLoanScope($user, $lockedLoan);
            abort_unless($lockedLoan->status === 'approved', 409, 'Only approved loans can have repayments reconciled.');
            $lockedInstallment = LoanAdvanceInstallment::query()->whereKey($installment->id)->lockForUpdate()->firstOrFail();
            $paidCents = $lockedInstallment->repayments()->get()->sum(fn ($repayment) => $this->toCents($repayment->amount));
            $installmentCents = $this->toCents($lockedInstallment->amount);
            $paymentCents = $this->toCents($data['amount']);
            if ($paymentCents > $installmentCents - $paidCents) {
                throw ValidationException::withMessages([
                    'amount' => ['The recorded amount cannot exceed the remaining scheduled installment.'],
                ]);
            }

            $repayment = $lockedInstallment->repayments()->create([
                'recorded_by' => $user->id,
                'amount' => $data['amount'],
                'recorded_date' => $data['recorded_date'],
                'reference' => trim($data['reference'] ?? '') ?: null,
            ]);
            AuditTrail::record('loans.repayment_reconciled', $lockedLoan, [
                'installment_id' => $lockedInstallment->id,
                'repayment_id' => $repayment->id,
                'amount' => $repayment->amount,
                'recorded_date' => $data['recorded_date'],
            ]);
            $totalRepaidCents = $lockedLoan->installments()
                ->with('repayments:id,loan_advance_installment_id,amount')
                ->get()
                ->sum(fn (LoanAdvanceInstallment $row) => $row->repayments->sum(fn ($item) => $this->toCents($item->amount)));
            if ($totalRepaidCents >= $this->toCents($lockedLoan->approved_amount)) {
                $lockedLoan->update(['status' => 'repaid']);
                AuditTrail::record('loans.advance_repaid', $lockedLoan, ['total_repaid' => $lockedLoan->approved_amount]);
            }
        });

        return response()->json(['loan' => $this->decorateLoan(
            $loan->fresh()->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'installments.repayments.recorder:id,name']),
            $user,
        )]);
    }

    private function loansFor($user): Builder
    {
        return LoanAdvance::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', PeopleScope::employees($user)->select('id'));
    }

    private function assertLoanScope($user, LoanAdvance $loan): void
    {
        abort_unless((int) $loan->company_id === (int) $user->company_id, 404);
        abort_unless(PeopleScope::employees($user)->whereKey($loan->employee_id)->exists(), 404);
    }

    private function ensureAccess($user): void
    {
        abort_unless(in_array($user->role, ['employee', 'manager', 'hr', 'admin'], true), 403, 'Loan and advance requests are not available for this role.');
    }

    private function decorateLoan(LoanAdvance $loan, $user): LoanAdvance
    {
        $loan->setAttribute(
            'can_review',
            $loan->status === 'pending'
                && (int) $loan->submitted_by !== (int) $user->id
                && PeopleScope::canReview($user, $loan->employee),
        );
        $loan->setAttribute('can_record_repayment', $user->canManagePeople() && $loan->status === 'approved');
        $totalPaid = 0;
        foreach ($loan->installments as $installment) {
            $paid = $installment->repayments->sum(fn ($repayment) => $this->toCents($repayment->amount));
            $installment->setAttribute('repaid_amount', $this->fromCents($paid));
            $installment->setAttribute('remaining_amount', $this->fromCents(max(0, $this->toCents($installment->amount) - $paid)));
            $totalPaid += $paid;
        }
        $loan->setAttribute('total_repaid', $this->fromCents($totalPaid));
        $loan->setAttribute('outstanding_amount', $loan->approved_amount
            ? $this->fromCents(max(0, $this->toCents($loan->approved_amount) - $totalPaid))
            : null);
        $loan->setAttribute('repayment_status', in_array($loan->status, ['approved', 'repaid'], true)
            ? ($totalPaid >= $this->toCents($loan->approved_amount) ? 'repaid' : 'active')
            : null);

        return $loan;
    }

    private function toCents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function fromCents(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }
}
