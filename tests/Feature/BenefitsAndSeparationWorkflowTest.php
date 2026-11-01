<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BenefitsAndSeparationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_configure_and_review_manual_benefit_enrollments(): void
    {
        [$company, $branch, $hr, $employee] = $this->createPeopleContext();
        $otherCurrencyEmployee = $this->createEmployee($company, $branch, 'OTHER-01', 'INR');
        $this->actingAs($hr);

        $plan = $this->postJson('/api/benefits/plans', [
            'name' => 'Health support',
            'category' => 'insurance',
            'currency' => 'AED',
            'branch_id' => $branch->id,
            'provider' => 'Internal provider record',
            'eligibility_notes' => 'Use approved internal policy.',
        ])->assertCreated()
            ->assertJsonPath('plan.status', 'active')
            ->assertJsonPath('plan.currency', 'AED')
            ->json('plan');

        $enrollmentPayload = [
            'benefit_plan_id' => $plan['id'],
            'employee_id' => $employee->id,
            'effective_start' => today()->toDateString(),
        ];
        $this->postJson('/api/benefits/enrollments', [
            ...$enrollmentPayload,
            'employee_id' => $otherCurrencyEmployee->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');

        $enrollment = $this->postJson('/api/benefits/enrollments', $enrollmentPayload)
            ->assertCreated()
            ->assertJsonPath('enrollment.status', 'draft')
            ->json('enrollment');
        $enrollmentId = $enrollment['id'];

        $this->postJson("/api/benefits/enrollments/{$enrollmentId}/submit")
            ->assertStatus(422);
        $this->postJson('/api/benefits/enrollments', [
            ...$enrollmentPayload,
            'effective_start' => today()->addDays(1)->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('effective_start');

        $this->patchJson("/api/benefits/enrollments/{$enrollmentId}", [
            'employee_contribution' => '125.00',
            'employer_contribution' => '250.00',
            'effective_start' => today()->toDateString(),
            'notes' => 'Manually verified inputs.',
        ])->assertOk()
            ->assertJsonPath('enrollment.employee_contribution', '125.00')
            ->assertJsonPath('enrollment.employer_contribution', '250.00');

        $this->postJson("/api/benefits/enrollments/{$enrollmentId}/submit")
            ->assertOk()
            ->assertJsonPath('enrollment.status', 'in_review');
        $this->patchJson("/api/benefits/enrollments/{$enrollmentId}", [
            'employee_contribution' => '130.00',
            'employer_contribution' => '250.00',
        ])->assertStatus(409);

        $this->postJson("/api/benefits/enrollments/{$enrollmentId}/review", [
            'decision' => 'return',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('review_note');
        $this->postJson("/api/benefits/enrollments/{$enrollmentId}/review", [
            'decision' => 'return',
            'review_note' => 'Confirm the employee contribution.',
        ])->assertOk()
            ->assertJsonPath('enrollment.status', 'changes_requested');

        $this->patchJson("/api/benefits/enrollments/{$enrollmentId}", [
            'employee_contribution' => '130.00',
            'employer_contribution' => '250.00',
            'effective_start' => today()->toDateString(),
        ])->assertOk();
        $this->postJson("/api/benefits/enrollments/{$enrollmentId}/submit")
            ->assertOk()
            ->assertJsonPath('enrollment.status', 'in_review');
        $this->postJson("/api/benefits/enrollments/{$enrollmentId}/review", [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('enrollment.status', 'approved');
        $this->postJson("/api/benefits/enrollments/{$enrollmentId}/end", [
            'effective_end' => today()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('enrollment.status', 'ended');
        $this->patchJson("/api/benefits/plans/{$plan['id']}", [
            'currency' => 'SAR',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
    }

    public function test_branch_hr_and_employees_cannot_cross_benefit_access_boundaries(): void
    {
        [$company, $branch, $hr, $employee] = $this->createPeopleContext();
        $branchHr = User::create([
            'name' => 'Branch HR',
            'email' => 'branch.hr@benefits.example.test',
            'password' => 'branch-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $employeeUser = User::create([
            'name' => 'Employee User',
            'email' => 'employee@benefits.example.test',
            'password' => 'employee-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'employee',
        ]);
        $plan = $this->actingAs($hr)->postJson('/api/benefits/plans', [
            'name' => 'Company-wide support',
            'category' => 'wellbeing',
            'currency' => 'AED',
        ])->assertCreated()->json('plan');

        $this->actingAs($branchHr)->getJson('/api/benefits')
            ->assertOk()
            ->assertJsonCount(1, 'plans')
            ->assertJsonPath('plans.0.branch_id', null)
            ->assertJsonCount(1, 'employees');
        $this->patchJson("/api/benefits/plans/{$plan['id']}", ['name' => 'Changed'])
            ->assertForbidden();
        $this->actingAs($employeeUser)->getJson('/api/benefits')->assertForbidden();
    }

    public function test_settlement_submission_requires_completed_clearances_and_saved_manual_lines(): void
    {
        [, , $hr, $employee] = $this->createPeopleContext();
        $this->actingAs($hr);
        $case = $this->postJson('/api/lifecycle/cases', [
            'employee_id' => $employee->id,
            'type' => 'offboarding',
            'effective_date' => today()->addDays(5)->toDateString(),
        ])->assertCreated()->json('case');

        $settlement = $this->postJson('/api/separations/settlements', [
            'lifecycle_case_id' => $case['id'],
            'notes' => 'Prepare after clearance.',
        ])->assertCreated()
            ->assertJsonPath('settlement.status', 'draft')
            ->json('settlement');
        $settlementId = $settlement['id'];

        $this->putJson("/api/separations/settlements/{$settlementId}/items", [
            'items' => [
                ['category' => 'earning', 'description' => 'Approved adjustment', 'amount' => '300.00'],
                ['category' => 'recovery', 'description' => 'Documented recovery', 'amount' => '25.00'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'settlement.items');
        $this->postJson("/api/separations/settlements/{$settlementId}/submit")
            ->assertStatus(422);

        foreach ($case['tasks'] as $task) {
            $this->patchJson("/api/lifecycle/tasks/{$task['id']}", [
                'status' => 'completed',
            ])->assertOk();
        }
        $this->postJson("/api/lifecycle/cases/{$case['id']}/complete", [
            'confirm_deactivation' => true,
        ])->assertOk()
            ->assertJsonPath('case.status', 'completed');

        $this->postJson("/api/separations/settlements/{$settlementId}/submit")
            ->assertOk()
            ->assertJsonPath('settlement.status', 'in_review');
        $this->putJson("/api/separations/settlements/{$settlementId}/items", [
            'items' => [
                ['category' => 'earning', 'description' => 'Changed while in review', 'amount' => '300.00'],
            ],
        ])->assertStatus(409);

        $this->postJson("/api/separations/settlements/{$settlementId}/review", [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('settlement.status', 'approved');
        $this->assertDatabaseHas('separation_settlement_items', [
            'separation_settlement_id' => $settlementId,
            'description' => 'Approved adjustment',
            'amount' => '300.00',
        ]);
    }

    private function createPeopleContext(): array
    {
        $company = Company::create([
            'name' => 'Benefits and Separation Company',
            'code' => 'BENSEP',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'BENSEP-01',
            'city' => 'Dubai',
        ]);
        $hr = User::create([
            'name' => 'Benefits HR',
            'email' => 'hr@benefits.example.test',
            'password' => 'benefits-password',
            'company_id' => $company->id,
            'role' => 'hr',
        ]);
        $employee = $this->createEmployee($company, $branch, 'BEN-001', 'AED');

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
