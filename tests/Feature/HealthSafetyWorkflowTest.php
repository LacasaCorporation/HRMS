<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SafetyRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthSafetyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_records_and_assigns_a_safety_follow_up_then_resolves_it(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'safety-hr@test.example', 'hr');
        [$assignee] = $this->person($company, $branch, 'safety-admin@test.example', 'admin');
        [, $employee] = $this->person($company, $branch, 'safety-employee@test.example', 'employee');
        $this->actingAs($hr);

        $record = $this->postJson('/api/health-safety/records', [
            'record_type' => 'hazard',
            'occurred_on' => today()->subDay()->toDateString(),
            'location' => 'Warehouse aisle 4',
            'priority' => 'high',
            'summary' => 'A reported obstruction was observed in the marked walkway.',
            'employee_id' => $employee->id,
        ])->assertCreated()
            ->assertJsonPath('record.status', 'open')
            ->assertJsonPath('record.priority', 'high')
            ->assertJsonPath('record.employee.id', $employee->id)
            ->json('record');

        $this->getJson('/api/health-safety')
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('meta.open', 1)
            ->assertJsonFragment(['id' => $assignee->id, 'name' => $assignee->name]);

        $this->postJson("/api/health-safety/records/{$record['id']}/resolve", [
            'resolution_summary' => 'Cannot resolve before follow-up is assigned.',
        ])->assertStatus(409);

        $this->postJson("/api/health-safety/records/{$record['id']}/assign", [
            'assigned_to' => $assignee->id,
            'action_plan' => 'Clear the walkway and document a follow-up check.',
        ])->assertOk()
            ->assertJsonPath('record.status', 'in_progress')
            ->assertJsonPath('record.assignee.id', $assignee->id)
            ->assertJsonPath('record.action_plan', 'Clear the walkway and document a follow-up check.');

        $this->postJson("/api/health-safety/records/{$record['id']}/resolve", [
            'resolution_summary' => 'Obstruction removed and the walkway was checked.',
        ])->assertOk()
            ->assertJsonPath('record.status', 'resolved')
            ->assertJsonPath('record.resolver.id', $hr->id);

        $this->postJson("/api/health-safety/records/{$record['id']}/resolve", [
            'resolution_summary' => 'Duplicate closure.',
        ])->assertStatus(409);
        $this->assertDatabaseHas('safety_records', [
            'id' => $record['id'],
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'resolved',
        ]);
    }

    public function test_safety_intake_validates_type_date_priority_and_active_employee_scope(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'safety-hr@test.example', 'hr');
        [, $employee] = $this->person($company, $branch, 'safety-employee@test.example', 'employee');
        $this->actingAs($hr);

        $this->postJson('/api/health-safety/records', [
            'record_type' => 'medical',
            'occurred_on' => today()->addDay()->toDateString(),
            'priority' => 'automatic',
            'summary' => 'Invalid safety report.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['record_type', 'occurred_on', 'priority']);

        $inactive = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_code' => 'SAFE-INACTIVE',
            'first_name' => 'Former',
            'last_name' => 'Employee',
            'status' => 'inactive',
        ]);
        $this->postJson('/api/health-safety/records', [
            'record_type' => 'incident',
            'occurred_on' => today()->subDay()->toDateString(),
            'priority' => 'normal',
            'summary' => 'Report for inactive employee.',
            'employee_id' => $inactive->id,
        ])->assertNotFound();

        $this->postJson('/api/health-safety/records', [
            'record_type' => 'inspection',
            'occurred_on' => today()->toDateString(),
            'priority' => 'normal',
            'summary' => 'Valid inspection finding.',
            'employee_id' => $employee->id,
        ])->assertCreated();
    }

    public function test_non_hr_roles_cannot_access_health_and_safety_records_or_actions(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'safety-hr@test.example', 'hr');
        [$manager] = $this->person($company, $branch, 'safety-manager@test.example', 'manager');
        [, $employee] = $this->person($company, $branch, 'safety-employee@test.example', 'employee');
        $record = SafetyRecord::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'reported_by' => $hr->id,
            'record_type' => 'incident',
            'occurred_on' => today()->subDay(),
            'priority' => 'normal',
            'summary' => 'Restricted safety item.',
            'status' => 'open',
        ]);

        $this->actingAs($manager)->getJson('/api/health-safety')->assertForbidden();
        $this->postJson("/api/health-safety/records/{$record->id}/assign", [
            'assigned_to' => $hr->id,
            'action_plan' => 'Unauthorized action.',
        ])->assertForbidden();
        $this->actingAs($employee->user)->getJson('/api/health-safety')->assertForbidden();
    }

    public function test_branch_hr_cannot_access_safety_records_or_assignees_outside_scope(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'safety-hr@test.example', 'hr');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Site',
            'code' => 'SAFE-OTHER',
        ]);
        [$otherHr] = $this->person($company, $otherBranch, 'other-safety-hr@test.example', 'hr');
        [, $employee] = $this->person($company, $otherBranch, 'other-safety-employee@test.example', 'employee');
        $record = SafetyRecord::create([
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'employee_id' => $employee->id,
            'reported_by' => $otherHr->id,
            'record_type' => 'ppe',
            'occurred_on' => today()->subDay(),
            'priority' => 'low',
            'summary' => 'Other branch record.',
            'status' => 'open',
        ]);

        $this->actingAs($hr)->getJson('/api/health-safety')
            ->assertOk()
            ->assertJsonCount(0, 'records')
            ->assertJsonCount(1, 'assignees')
            ->assertJsonMissing(['id' => $otherHr->id, 'name' => $otherHr->name]);
        $this->postJson("/api/health-safety/records/{$record->id}/assign", [
            'assigned_to' => $hr->id,
            'action_plan' => 'Out of scope.',
        ])->assertNotFound();
    }

    private function companyAndBranch(): array
    {
        $company = Company::create([
            'name' => 'Safety Test Company',
            'code' => 'SAFETY',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Site',
            'code' => 'SAFE-MAIN',
        ]);

        return [$company, $branch];
    }

    private function person(Company $company, Branch $branch, string $email, string $role): array
    {
        $name = ucfirst(strtok($email, '@'));
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'employee_code' => strtoupper(substr(hash('sha256', $email), 0, 8)),
            'first_name' => $name,
            'last_name' => 'Person',
            'department' => 'Operations',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }
}
