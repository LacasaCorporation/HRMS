<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_a_private_receipt_claim_and_only_see_their_own_records(): void
    {
        Storage::fake('local');
        [$company, $branch] = $this->companyAndBranch();
        [$employeeUser, $employee] = $this->employee($company, $branch, 'EXP-001', 'Avery', 'Employee');
        [, $otherEmployee] = $this->employee($company, $branch, 'EXP-002', 'Other', 'Employee');
        $this->createClaim($company, $branch, $otherEmployee, $otherEmployee->user, 'Other private merchant');

        $response = $this->actingAs($employeeUser)->post('/api/expenses', [
            'category' => 'transport',
            'merchant' => 'City Cab',
            'expense_date' => today()->toDateString(),
            'amount' => '18.50',
            'description' => 'Travel to the customer site',
            'receipt' => UploadedFile::fake()->create('cab.pdf', 24, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('claim.status', 'pending')
            ->assertJsonPath('claim.currency', 'AED')
            ->assertJsonPath('claim.receipt_original_name', 'cab.pdf');
        $claim = ExpenseClaim::findOrFail($response->json('claim.id'));
        Storage::disk('local')->assertExists($claim->receipt_path);

        $this->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(1, 'claims')
            ->assertJsonPath('claims.0.employee_id', $employee->id)
            ->assertJsonPath('meta.pending', 1);
        $this->get('/api/expenses/'.$claim->id.'/receipt')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_manager_can_review_direct_report_claims_but_not_own_or_unrelated_claims(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$managerUser, $manager] = $this->employee($company, $branch, 'EXP-MGR', 'Morgan', 'Manager', 'manager');
        [$reportUser, $report] = $this->employee($company, $branch, 'EXP-REP', 'Riley', 'Report');
        $report->update(['manager_id' => $manager->id]);
        [, $unrelated] = $this->employee($company, $branch, 'EXP-OTH', 'Una', 'Related');
        $reportClaim = $this->createClaim($company, $branch, $report, $reportUser);
        $ownClaim = $this->createClaim($company, $branch, $manager, $managerUser);
        $unrelatedClaim = $this->createClaim($company, $branch, $unrelated, $unrelated->user);

        $this->actingAs($managerUser)->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(2, 'claims')
            ->assertJsonFragment(['can_review' => true]);

        $this->postJson("/api/expenses/{$reportClaim->id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('claim.status', 'approved')
            ->assertJsonPath('claim.reviewed_by', $managerUser->id);
        $this->postJson("/api/expenses/{$ownClaim->id}/review", [
            'decision' => 'approve',
        ])->assertForbidden();
        $this->postJson("/api/expenses/{$unrelatedClaim->id}/review", [
            'decision' => 'approve',
        ])->assertNotFound();
        $this->postJson("/api/expenses/{$reportClaim->id}/review", [
            'decision' => 'approve',
        ])->assertStatus(409);
    }

    public function test_rejection_requires_a_reason_and_submission_validates_amount_and_date(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$employeeUser, $employee] = $this->employee($company, $branch, 'EXP-001', 'Avery', 'Employee');
        [$hrUser] = $this->employee($company, $branch, 'EXP-HR', 'Harper', 'HR', 'hr');
        $claim = $this->createClaim($company, $branch, $employee, $employeeUser);

        $this->actingAs($employeeUser)->postJson('/api/expenses', [
            'category' => 'meals',
            'merchant' => 'Cafe',
            'expense_date' => today()->addDay()->toDateString(),
            'amount' => '-1',
            'description' => 'Team meeting',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['expense_date', 'amount']);

        $this->actingAs($hrUser)->postJson("/api/expenses/{$claim->id}/review", [
            'decision' => 'reject',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['review_note']);
        $this->postJson("/api/expenses/{$claim->id}/review", [
            'decision' => 'reject',
            'review_note' => 'Receipt is missing the business purpose.',
        ])->assertOk()
            ->assertJsonPath('claim.status', 'rejected')
            ->assertJsonPath('claim.review_note', 'Receipt is missing the business purpose.');
    }

    public function test_receipt_download_is_hidden_outside_the_employees_people_scope(): void
    {
        Storage::fake('local');
        [$company, $branch] = $this->companyAndBranch();
        [$employeeUser, $employee] = $this->employee($company, $branch, 'EXP-001', 'Avery', 'Employee');
        [$otherUser, $otherEmployee] = $this->employee($company, $branch, 'EXP-002', 'Other', 'Employee');
        $claim = $this->createClaim($company, $branch, $employee, $employeeUser, receipt: true);
        Storage::disk('local')->put($claim->receipt_path, 'private receipt');

        $this->actingAs($otherUser)->get("/api/expenses/{$claim->id}/receipt")->assertNotFound();
        $this->actingAs($employeeUser)->get("/api/expenses/{$claim->id}/receipt")->assertOk();
        $this->assertNotSame($employee->id, $otherEmployee->id);
    }

    public function test_branch_hr_cannot_view_or_review_claims_from_another_branch(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hrUser] = $this->employee($company, $branch, 'EXP-HR', 'Harper', 'HR', 'hr');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Branch',
            'code' => 'EXPC-02',
            'city' => 'Abu Dhabi',
        ]);
        [$employeeUser, $employee] = $this->employee($company, $otherBranch, 'EXP-002', 'Other', 'Branch');
        $claim = $this->createClaim($company, $otherBranch, $employee, $employeeUser);

        $this->actingAs($hrUser)->getJson('/api/expenses')
            ->assertOk()
            ->assertJsonCount(0, 'claims')
            ->assertJsonPath('meta.total', 0);
        $this->postJson("/api/expenses/{$claim->id}/review", [
            'decision' => 'approve',
        ])->assertNotFound();
    }

    private function companyAndBranch(): array
    {
        $company = Company::create([
            'name' => 'Expense Company',
            'code' => 'EXPC',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'EXPC-01',
            'city' => 'Dubai',
        ]);

        return [$company, $branch];
    }

    private function employee(Company $company, Branch $branch, string $code, string $first, string $last, string $role = 'employee'): array
    {
        $user = User::create([
            'name' => "{$first} {$last}",
            'email' => strtolower($code).'@expense.test',
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

    private function createClaim(Company $company, Branch $branch, Employee $employee, User $submitter, string $merchant = 'Test merchant', bool $receipt = false): ExpenseClaim
    {
        return ExpenseClaim::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'submitted_by' => $submitter->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => trim($employee->first_name.' '.$employee->last_name),
            'department' => $employee->department,
            'category' => 'other',
            'merchant' => $merchant,
            'expense_date' => today()->toDateString(),
            'amount' => '25.00',
            'currency' => 'AED',
            'description' => 'Expense test record',
            'receipt_path' => $receipt ? 'expense-receipts/'.$company->id.'/test.pdf' : null,
            'receipt_original_name' => $receipt ? 'test.pdf' : null,
            'receipt_mime' => $receipt ? 'application/pdf' : null,
            'receipt_size' => $receipt ? 15 : null,
            'status' => 'pending',
        ]);
    }
}
