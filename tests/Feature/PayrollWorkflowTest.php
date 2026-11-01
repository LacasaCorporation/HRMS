<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_prepare_review_return_and_approve_a_manual_payroll_workpaper(): void
    {
        [$company, $branch, $hr, $employee] = $this->createPayrollContext();
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        $cycleResponse = $this->actingAs($hr)->postJson('/api/payroll/cycles', [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'pay_date' => now()->endOfMonth()->toDateString(),
            'currency' => 'AED',
            'branch_id' => $branch->id,
            'employee_ids' => [$employee->id],
        ])->assertCreated()
            ->assertJsonPath('cycle.status', 'draft')
            ->assertJsonPath('cycle.currency', 'AED')
            ->assertJsonPath('cycle.branch_id', $branch->id)
            ->assertJsonPath('cycle.branch_scope_key', $branch->id)
            ->assertJsonPath('cycle.items.0.employee_code', $employee->employee_code);
        $cycleId = $cycleResponse->json('cycle.id');
        $itemId = $cycleResponse->json('cycle.items.0.id');
        $this->assertDatabaseHas('payroll_cycles', [
            'id' => $cycleId,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'currency' => 'AED',
        ]);

        $this->postJson('/api/payroll/cycles', [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'currency' => 'AED',
            'branch_id' => $branch->id,
            'employee_ids' => [$employee->id],
        ])->assertStatus(409);

        $this->postJson("/api/payroll/cycles/{$cycleId}/submit")
            ->assertStatus(422);

        $this->patchJson("/api/payroll/items/{$itemId}", [
            'base_pay' => 1200.5,
            'other_earnings' => 100,
            'manual_deductions' => 50.25,
            'notes' => 'Approved overtime input',
        ])->assertOk()
            ->assertJsonPath('item.gross_pay', '1300.50')
            ->assertJsonPath('item.net_pay', '1250.25');

        $this->patchJson("/api/payroll/items/{$itemId}", [
            'base_pay' => 1200,
            'other_earnings' => 0,
            'manual_deductions' => 1200.01,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('manual_deductions');

        $this->patchJson("/api/payroll/items/{$itemId}", [
            'base_pay' => '9999999999.99',
            'other_earnings' => '0.01',
            'manual_deductions' => '0',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('other_earnings');

        $this->postJson("/api/payroll/cycles/{$cycleId}/submit")
            ->assertOk()
            ->assertJsonPath('cycle.status', 'in_review');

        $this->patchJson("/api/payroll/items/{$itemId}", [
            'base_pay' => 100,
            'other_earnings' => 0,
            'manual_deductions' => 0,
        ])->assertStatus(409);

        $this->postJson("/api/payroll/cycles/{$cycleId}/review", [
            'decision' => 'return',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('review_note');

        $this->postJson("/api/payroll/cycles/{$cycleId}/review", [
            'decision' => 'return',
            'review_note' => 'Verify the other earnings backup.',
        ])->assertOk()
            ->assertJsonPath('cycle.status', 'changes_requested')
            ->assertJsonPath('cycle.review_note', 'Verify the other earnings backup.');

        $this->patchJson("/api/payroll/items/{$itemId}", [
            'base_pay' => 1200.5,
            'other_earnings' => 100,
            'manual_deductions' => 50.25,
            'notes' => 'Verified against approved input',
        ])->assertOk();

        $this->postJson("/api/payroll/cycles/{$cycleId}/submit")
            ->assertOk()
            ->assertJsonPath('cycle.status', 'in_review');

        $this->postJson("/api/payroll/cycles/{$cycleId}/review", [
            'decision' => 'approve',
            'review_note' => 'Inputs reviewed.',
        ])->assertOk()
            ->assertJsonPath('cycle.status', 'approved')
            ->assertJsonPath('cycle.review_note', 'Inputs reviewed.');

        $this->assertDatabaseHas('payroll_cycles', [
            'id' => $cycleId,
            'company_id' => $company->id,
            'status' => 'approved',
        ]);
    }

    public function test_payroll_is_restricted_to_hr_and_branch_users_cannot_access_company_wide_cycles(): void
    {
        [$company, $branch, $hr, $employee] = $this->createPayrollContext();
        $branchHr = User::create([
            'name' => 'Branch HR',
            'email' => 'branch.hr@example.test',
            'password' => 'branch-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $employeeUser = User::create([
            'name' => 'Employee User',
            'email' => 'employee@example.test',
            'password' => 'employee-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'employee',
        ]);

        $cycleResponse = $this->actingAs($hr)->postJson('/api/payroll/cycles', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'currency' => 'AED',
            'employee_ids' => [$employee->id],
        ])->assertCreated();
        $cycle = PayrollCycle::with('items')->findOrFail($cycleResponse->json('cycle.id'));
        $item = $cycle->items->firstOrFail();

        $this->actingAs($branchHr)->getJson('/api/payroll')
            ->assertOk()
            ->assertJsonCount(0, 'cycles')
            ->assertJsonCount(1, 'employees');
        $this->patchJson("/api/payroll/items/{$item->id}", [
            'base_pay' => 500,
            'other_earnings' => 0,
            'manual_deductions' => 0,
        ])->assertForbidden();
        $this->postJson("/api/payroll/cycles/{$cycle->id}/submit")->assertForbidden();
        $this->actingAs($employeeUser)->getJson('/api/payroll')->assertForbidden();

        $otherCompany = Company::create([
            'name' => 'Separate Payroll Company',
            'code' => 'SEP',
            'country' => 'IN',
            'currency' => 'INR',
        ]);
        $otherHr = User::create([
            'name' => 'Other Company HR',
            'email' => 'other.hr@example.test',
            'password' => 'other-password',
            'company_id' => $otherCompany->id,
            'role' => 'hr',
        ]);
        $this->actingAs($otherHr)->getJson('/api/payroll')
            ->assertOk()
            ->assertJsonCount(0, 'cycles')
            ->assertJsonCount(0, 'employees');
        $this->patchJson("/api/payroll/items/{$item->id}", [
            'base_pay' => 500,
            'other_earnings' => 0,
            'manual_deductions' => 0,
        ])->assertNotFound();
    }

    public function test_cycle_creation_rejects_employees_outside_currency_or_branch_scope(): void
    {
        [$company, $branch, $hr, $employee] = $this->createPayrollContext();
        $branchHr = User::create([
            'name' => 'Branch HR',
            'email' => 'branch.hr@example.test',
            'password' => 'branch-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Branch',
            'code' => 'OTH-01',
            'city' => 'Abu Dhabi',
        ]);
        $otherEmployee = $this->createEmployee($company, $otherBranch, 'OTH-01', 'SAR');

        $this->actingAs($branchHr)->postJson('/api/payroll/cycles', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'currency' => 'AED',
            'employee_ids' => [$otherEmployee->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_ids');

        $this->postJson('/api/payroll/cycles', [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'currency' => 'AED',
            'branch_id' => $otherBranch->id,
            'employee_ids' => [$employee->id],
        ])->assertForbidden();
    }

    private function createPayrollContext(): array
    {
        $company = Company::create([
            'name' => 'Payroll Test Company',
            'code' => 'PAY',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Dubai Branch',
            'code' => 'DXB-01',
            'city' => 'Dubai',
        ]);
        $hr = User::create([
            'name' => 'Payroll HR',
            'email' => 'payroll.hr@example.test',
            'password' => 'payroll-password',
            'company_id' => $company->id,
            'branch_id' => null,
            'role' => 'hr',
        ]);
        $employee = $this->createEmployee($company, $branch, 'PAY-001', 'AED');

        return [$company, $branch, $hr, $employee];
    }

    private function createEmployee(Company $company, Branch $branch, string $code, string $currency): Employee
    {
        return Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_code' => $code,
            'first_name' => 'Taylor',
            'last_name' => 'Employee',
            'department' => 'Operations',
            'status' => 'active',
            'currency' => $currency,
        ]);
    }
}
