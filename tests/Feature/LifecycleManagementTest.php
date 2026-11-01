<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LifecycleCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_start_and_complete_an_onboarding_checklist(): void
    {
        [$company, $branch, $hr, $employee] = $this->lifecycleFixture();
        $this->actingAs($hr);

        $caseResponse = $this->postJson('/api/lifecycle/cases', [
            'employee_id' => $employee->id,
            'type' => 'onboarding',
            'effective_date' => today()->addDays(14)->toDateString(),
            'notes' => 'Coordinate the first week with the manager.',
        ])->assertCreated()
            ->assertJsonPath('case.type', 'onboarding')
            ->assertJsonPath('case.status', 'in_progress')
            ->assertJsonPath('case.notes', 'Coordinate the first week with the manager.');

        $caseId = $caseResponse->json('case.id');
        $taskId = $caseResponse->json('case.tasks.0.id');
        $this->assertCount(6, $caseResponse->json('case.tasks'));
        $this->assertDatabaseHas('lifecycle_tasks', [
            'id' => $taskId,
            'owner_team' => 'People Operations',
            'status' => 'pending',
        ]);

        $this->postJson('/api/lifecycle/cases', [
            'employee_id' => $employee->id,
            'type' => 'onboarding',
            'effective_date' => today()->addDays(14)->toDateString(),
        ])->assertUnprocessable();

        $this->patchJson("/api/lifecycle/tasks/{$taskId}", [
            'status' => 'in_progress',
            'notes' => 'Paperwork is being checked.',
        ])->assertOk()
            ->assertJsonPath('task.status', 'in_progress')
            ->assertJsonPath('task.notes', 'Paperwork is being checked.');

        $this->postJson("/api/lifecycle/cases/{$caseId}/complete")
            ->assertUnprocessable();

        foreach ($caseResponse->json('case.tasks') as $task) {
            $this->patchJson("/api/lifecycle/tasks/{$task['id']}", [
                'status' => 'completed',
                'notes' => null,
            ])->assertOk();
        }

        $this->postJson("/api/lifecycle/cases/{$caseId}/complete")
            ->assertOk()
            ->assertJsonPath('case.status', 'completed');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('lifecycle_cases', [
            'id' => $caseId,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'completed',
        ]);

        $this->getJson('/api/lifecycle')
            ->assertOk()
            ->assertJsonPath('metrics.active_onboarding', 0)
            ->assertJsonPath('cases.0.status', 'completed');
    }

    public function test_offboarding_requires_all_clearances_and_explicit_deactivation_confirmation(): void
    {
        [, , $hr, $employee] = $this->lifecycleFixture();
        $this->actingAs($hr);

        $case = $this->postJson('/api/lifecycle/cases', [
            'employee_id' => $employee->id,
            'type' => 'offboarding',
            'effective_date' => today()->addDays(10)->toDateString(),
        ])->assertCreated()->json('case');

        $this->postJson("/api/lifecycle/cases/{$case['id']}/complete")
            ->assertUnprocessable();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'active']);

        foreach ($case['tasks'] as $task) {
            $this->patchJson("/api/lifecycle/tasks/{$task['id']}", [
                'status' => 'completed',
            ])->assertOk();
        }

        $this->postJson("/api/lifecycle/cases/{$case['id']}/complete")
            ->assertUnprocessable();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'active']);

        $this->postJson("/api/lifecycle/cases/{$case['id']}/complete", [
            'confirm_deactivation' => true,
        ])->assertOk()
            ->assertJsonPath('case.status', 'completed')
            ->assertJsonPath('case.employee.status', 'inactive');

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'status' => 'inactive']);
    }

    public function test_lifecycle_data_is_company_branch_scoped_and_hr_only(): void
    {
        [$company, $branch, $hr, $employee, $manager] = $this->lifecycleFixture();
        $case = LifecycleCase::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'created_by' => $hr->id,
            'type' => 'onboarding',
            'status' => 'in_progress',
            'effective_date' => today()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($manager)->getJson('/api/lifecycle')->assertForbidden();

        $foreignCompany = Company::create([
            'name' => 'Other Company',
            'code' => 'OTHER',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $foreignEmployee = Employee::create([
            'company_id' => $foreignCompany->id,
            'employee_code' => 'OTHER-01',
            'first_name' => 'Foreign',
            'last_name' => 'Employee',
            'employment_type' => 'Full-time',
            'status' => 'active',
        ]);

        $this->actingAs($hr)->getJson('/api/lifecycle')
            ->assertOk()
            ->assertJsonCount(1, 'cases')
            ->assertJsonPath('cases.0.id', $case->id)
            ->assertJsonCount(1, 'employees');

        $this->postJson('/api/lifecycle/cases', [
            'employee_id' => $foreignEmployee->id,
            'type' => 'offboarding',
            'effective_date' => today()->addDays(5)->toDateString(),
        ])->assertNotFound();
    }

    private function lifecycleFixture(): array
    {
        $company = Company::create([
            'name' => 'Lifecycle Test Company',
            'code' => 'LIFECYCLE',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'LIFECYCLE-MAIN',
        ]);
        $hr = User::create([
            'name' => 'Lifecycle HR',
            'email' => 'hr@lifecycle.example.test',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_code' => 'LIFE-01',
            'first_name' => 'Amina',
            'last_name' => 'Rahman',
            'work_email' => 'amina@lifecycle.example.test',
            'job_title' => 'People Coordinator',
            'department' => 'People & Culture',
            'employment_type' => 'Full-time',
            'status' => 'active',
            'join_date' => today()->toDateString(),
        ]);
        $manager = User::create([
            'name' => 'Lifecycle Manager',
            'email' => 'manager@lifecycle.example.test',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'manager',
        ]);

        return [$company, $branch, $hr, $employee, $manager];
    }
}
