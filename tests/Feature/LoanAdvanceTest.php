<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LoanAdvance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanAdvanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_one_scoped_request_and_cannot_submit_another_while_open(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$user, $employee] = $this->employee($company, $branch, 'LN-001', 'Avery', 'Employee');
        [$otherUser, $otherEmployee] = $this->employee($company, $branch, 'LN-002', 'Other', 'Employee');
        $this->createLoan($company, $branch, $otherEmployee, $otherUser);

        $this->actingAs($user)->postJson('/api/loans-advances', [
            'requested_amount' => '1250.50',
            'requested_installments' => 6,
            'purpose' => 'Emergency travel costs',
        ])->assertCreated()
            ->assertJsonPath('loan.employee_id', $employee->id)
            ->assertJsonPath('loan.status', 'pending')
            ->assertJsonPath('loan.currency', 'AED');

        $this->postJson('/api/loans-advances', [
            'requested_amount' => '300.00',
            'requested_installments' => 3,
            'purpose' => 'Another request',
        ])->assertStatus(409);

        $this->getJson('/api/loans-advances')
            ->assertOk()
            ->assertJsonCount(1, 'loans')
            ->assertJsonPath('loans.0.employee_id', $employee->id)
            ->assertJsonPath('meta.can_apply', false);
    }

    public function test_manager_can_review_direct_report_and_approval_builds_principal_only_schedule(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$managerUser, $manager] = $this->employee($company, $branch, 'LN-MGR', 'Morgan', 'Manager', 'manager');
        [$reportUser, $report] = $this->employee($company, $branch, 'LN-REP', 'Riley', 'Report');
        $report->update(['manager_id' => $manager->id]);
        [, $other] = $this->employee($company, $branch, 'LN-OTH', 'Una', 'Other');
        $loan = $this->createLoan($company, $branch, $report, $reportUser, '1000.01', 3);
        $otherLoan = $this->createLoan($company, $branch, $other, $other->user);

        $this->actingAs($managerUser)->getJson('/api/loans-advances')
            ->assertOk()
            ->assertJsonCount(1, 'loans')
            ->assertJsonFragment(['can_review' => true]);
        $this->postJson("/api/loans-advances/{$loan->id}/review", [
            'decision' => 'approve',
            'approved_amount' => '1000.01',
            'repayment_installments' => 3,
            'first_due_date' => '2027-01-31',
            'review_note' => 'Approved principal only.',
        ])->assertOk()
            ->assertJsonPath('loan.status', 'approved')
            ->assertJsonPath('loan.installments.0.amount', '333.34')
            ->assertJsonPath('loan.installments.1.amount', '333.34')
            ->assertJsonPath('loan.installments.2.amount', '333.33')
            ->assertJsonPath('loan.installments.0.due_date', '2027-01-31T00:00:00.000000Z')
            ->assertJsonPath('loan.installments.1.due_date', '2027-02-28T00:00:00.000000Z');

        $this->postJson("/api/loans-advances/{$otherLoan->id}/review", [
            'decision' => 'reject',
            'review_note' => 'Not in my reporting scope.',
        ])->assertNotFound();
    }

    public function test_rejection_requires_a_reason_and_approved_amount_cannot_exceed_request(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hrUser] = $this->employee($company, $branch, 'LN-HR', 'Harper', 'HR', 'hr');
        [$employeeUser, $employee] = $this->employee($company, $branch, 'LN-EMP', 'Evan', 'Employee');
        $loan = $this->createLoan($company, $branch, $employee, $employeeUser, '100.00', 3);

        $this->actingAs($hrUser)->postJson("/api/loans-advances/{$loan->id}/review", [
            'decision' => 'reject',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['review_note']);

        $this->postJson("/api/loans-advances/{$loan->id}/review", [
            'decision' => 'approve',
            'approved_amount' => '100.01',
            'repayment_installments' => 3,
            'first_due_date' => today()->addMonthNoOverflow()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['approved_amount']);

        $this->postJson("/api/loans-advances/{$loan->id}/review", [
            'decision' => 'reject',
            'review_note' => 'Not approved under current policy.',
        ])->assertOk()
            ->assertJsonPath('loan.status', 'rejected')
            ->assertJsonPath('loan.review_note', 'Not approved under current policy.');
    }

    public function test_hr_records_external_repayments_without_overpaying_or_triggering_payment(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hrUser] = $this->employee($company, $branch, 'LN-HR', 'Harper', 'HR', 'hr');
        [$employeeUser, $employee] = $this->employee($company, $branch, 'LN-EMP', 'Evan', 'Employee');
        $loan = $this->createLoan($company, $branch, $employee, $employeeUser, '100.00', 2, 'approved');
        $loan->update([
            'approved_amount' => '100.00',
            'repayment_installments' => 2,
            'first_due_date' => today()->addMonthNoOverflow()->toDateString(),
            'reviewed_by' => $hrUser->id,
            'reviewed_at' => now(),
        ]);
        $first = $loan->installments()->create(['sequence' => 1, 'due_date' => today()->addMonthNoOverflow(), 'amount' => '50.00']);
        $second = $loan->installments()->create(['sequence' => 2, 'due_date' => today()->addMonthsNoOverflow(2), 'amount' => '50.00']);

        $this->actingAs($employeeUser)->postJson("/api/loans-advances/installments/{$first->id}/repayments", [
            'amount' => '10.00',
            'recorded_date' => today()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($hrUser)->postJson("/api/loans-advances/installments/{$first->id}/repayments", [
            'amount' => '50.01',
            'recorded_date' => today()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->postJson("/api/loans-advances/installments/{$first->id}/repayments", [
            'amount' => '20.00',
            'recorded_date' => today()->toDateString(),
            'reference' => 'External receipt 701',
        ])->assertOk()
            ->assertJsonPath('loan.total_repaid', '20.00')
            ->assertJsonPath('loan.outstanding_amount', '80.00');
        $this->postJson("/api/loans-advances/installments/{$first->id}/repayments", [
            'amount' => '30.00',
            'recorded_date' => today()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('loan.installments.0.remaining_amount', '0.00');
        $this->postJson("/api/loans-advances/installments/{$second->id}/repayments", [
            'amount' => '50.00',
            'recorded_date' => today()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('loan.status', 'repaid');

        $this->getJson('/api/loans-advances')
            ->assertOk()
            ->assertJsonPath('loans.0.status', 'repaid')
            ->assertJsonPath('loans.0.outstanding_amount', '0.00')
            ->assertJsonPath('meta.can_apply', true);
    }

    public function test_branch_hr_cannot_review_or_reconcile_another_branch_loan(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hrUser] = $this->employee($company, $branch, 'LN-HR', 'Harper', 'HR', 'hr');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Branch',
            'code' => 'LOAN-02',
            'city' => 'Abu Dhabi',
        ]);
        [$employeeUser, $employee] = $this->employee($company, $otherBranch, 'LN-EMP', 'Evan', 'Employee');
        $loan = $this->createLoan($company, $otherBranch, $employee, $employeeUser, '100.00', 2, 'approved');
        $loan->update(['approved_amount' => '100.00', 'repayment_installments' => 2, 'first_due_date' => today()]);
        $installment = $loan->installments()->create(['sequence' => 1, 'due_date' => today(), 'amount' => '50.00']);

        $this->actingAs($hrUser)->getJson('/api/loans-advances')
            ->assertOk()
            ->assertJsonCount(0, 'loans');
        $this->postJson("/api/loans-advances/{$loan->id}/review", ['decision' => 'reject', 'review_note' => 'Out of scope'])
            ->assertNotFound();
        $this->postJson("/api/loans-advances/installments/{$installment->id}/repayments", [
            'amount' => '50.00',
            'recorded_date' => today()->toDateString(),
        ])->assertNotFound();
    }

    private function companyAndBranch(): array
    {
        $company = Company::create(['name' => 'Loan Company', 'code' => 'LOAN', 'country' => 'AE', 'currency' => 'AED']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Main Branch', 'code' => 'LOAN-01', 'city' => 'Dubai']);

        return [$company, $branch];
    }

    private function employee(Company $company, Branch $branch, string $code, string $first, string $last, string $role = 'employee'): array
    {
        $user = User::create([
            'name' => "{$first} {$last}",
            'email' => strtolower($code).'@loan.test',
            'password' => 'test-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'department' => 'Operations',
            'currency' => 'AED',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function createLoan(Company $company, Branch $branch, Employee $employee, User $submitter, string $amount = '250.00', int $installments = 5, string $status = 'pending'): LoanAdvance
    {
        return LoanAdvance::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'submitted_by' => $submitter->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => trim($employee->first_name.' '.$employee->last_name),
            'department' => $employee->department,
            'currency' => 'AED',
            'requested_amount' => $amount,
            'requested_installments' => $installments,
            'purpose' => 'Loan feature test request',
            'status' => $status,
        ]);
    }
}
