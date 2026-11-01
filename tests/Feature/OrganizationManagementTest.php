<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_create_and_update_departments_without_circular_parent_links(): void
    {
        [$company, $branch, $hr, $manager, $employee] = $this->organizationFixture();
        $this->actingAs($hr);

        $parent = $this->postJson('/api/organization/departments', [
            'name' => 'Operations',
            'code' => 'OPS',
            'branch_id' => $branch->id,
            'cost_center' => 'CC-100',
            'manager_employee_id' => $manager->id,
        ])->assertCreated()
            ->assertJsonPath('department.name', 'Operations')
            ->assertJsonPath('department.cost_center', 'CC-100');

        $parentId = $parent->json('department.id');
        $child = $this->postJson('/api/organization/departments', [
            'name' => 'Showroom',
            'code' => 'SHOW',
            'branch_id' => $branch->id,
            'parent_id' => $parentId,
        ])->assertCreated();
        $childId = $child->json('department.id');

        $this->patchJson("/api/organization/departments/{$parentId}", [
            'name' => 'Operations',
            'code' => 'OPS',
            'branch_id' => $branch->id,
            'parent_id' => $childId,
            'manager_employee_id' => $manager->id,
            'cost_center' => 'CC-100',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('departments', [
            'id' => $childId,
            'company_id' => $company->id,
            'parent_id' => $parentId,
        ]);
    }

    public function test_hr_can_create_positions_assign_people_and_respect_headcount(): void
    {
        [, $branch, $hr, , $employee] = $this->organizationFixture();
        $department = Department::create([
            'company_id' => $hr->company_id,
            'branch_id' => $branch->id,
            'name' => 'Manufacturing',
            'code' => 'MFG',
            'status' => 'active',
        ]);
        $this->actingAs($hr);

        $position = $this->postJson('/api/organization/positions', [
            'title' => 'Production Supervisor',
            'code' => 'MFG-SUP',
            'department_id' => $department->id,
            'branch_id' => $branch->id,
            'employment_type' => 'Full-time',
            'headcount' => 1,
        ])->assertCreated();
        $positionId = $position->json('position.id');

        $this->postJson("/api/organization/positions/{$positionId}/assign", [
            'employee_id' => $employee->id,
        ])->assertOk()
            ->assertJsonPath('employee.position.title', 'Production Supervisor')
            ->assertJsonPath('employee.department_record.name', 'Manufacturing');

        $secondEmployee = Employee::create([
            'company_id' => $hr->company_id,
            'branch_id' => $branch->id,
            'employee_code' => 'ORG-EMP-02',
            'first_name' => 'Second',
            'last_name' => 'Employee',
            'status' => 'active',
        ]);

        $this->postJson("/api/organization/positions/{$positionId}/assign", [
            'employee_id' => $secondEmployee->id,
        ])->assertStatus(409);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'position_id' => $positionId,
            'department_id' => $department->id,
            'job_title' => 'Production Supervisor',
        ]);
    }

    public function test_reporting_line_updates_are_scoped_and_reject_cycles(): void
    {
        [, , $hr, $managerUser, $employee] = $this->organizationFixture();
        $hrEmployee = $hr->employee;
        $managerEmployee = $managerUser->employee;

        $this->actingAs($hr)
            ->patchJson("/api/organization/employees/{$managerEmployee->id}/reporting-line", [
                'manager_id' => $hrEmployee->id,
            ])->assertOk();

        $this->patchJson("/api/organization/employees/{$employee->id}/reporting-line", [
            'manager_id' => $managerEmployee->id,
        ])->assertOk();

        $this->patchJson("/api/organization/employees/{$hrEmployee->id}/reporting-line", [
            'manager_id' => $employee->id,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('employees', [
            'id' => $hrEmployee->id,
            'manager_id' => null,
        ]);

        $this->actingAs($managerUser)
            ->patchJson("/api/organization/employees/{$employee->id}/reporting-line", [
                'manager_id' => $hrEmployee->id,
            ])->assertForbidden();
    }

    public function test_organization_overview_is_company_scoped_and_writes_are_for_hr_only(): void
    {
        [$company, $branch, $hr, , $employee] = $this->organizationFixture();
        $department = Department::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Retail',
            'code' => 'RTL',
            'status' => 'active',
        ]);
        $employee->update(['department_id' => $department->id]);
        $foreignCompany = Company::create([
            'name' => 'Other Company',
            'code' => 'OTHER',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        Department::create([
            'company_id' => $foreignCompany->id,
            'name' => 'Private Department',
            'code' => 'PRIVATE',
            'status' => 'active',
        ]);

        $this->actingAs($hr)
            ->getJson('/api/organization')
            ->assertOk()
            ->assertJsonPath('metrics.employees', 3)
            ->assertJsonPath('metrics.departments', 1)
            ->assertJsonCount(1, 'departments')
            ->assertJsonMissing(['name' => 'Private Department']);

        $this->actingAs($employee->user)
            ->postJson('/api/organization/departments', [
                'name' => 'Unauthorized',
                'code' => 'NOPE',
            ])->assertForbidden();

        $this->assertDatabaseMissing('departments', ['code' => 'NOPE']);
    }

    private function organizationFixture(): array
    {
        $company = Company::create([
            'name' => 'Organization Test Company',
            'code' => 'ORG',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'ORG-MAIN',
            'city' => 'Dubai',
        ]);
        $hr = User::create([
            'name' => 'HR User',
            'email' => 'hr@organization.example.test',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $managerUser = User::create([
            'name' => 'Manager User',
            'email' => 'manager@organization.example.test',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'manager',
        ]);
        $employeeUser = User::create([
            'name' => 'Employee User',
            'email' => 'employee@organization.example.test',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'employee',
        ]);

        $hrEmployee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $hr->id,
            'employee_code' => 'ORG-HR-01',
            'first_name' => 'HR',
            'last_name' => 'User',
            'status' => 'active',
        ]);
        $managerEmployee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $managerUser->id,
            'employee_code' => 'ORG-MGR-01',
            'first_name' => 'Manager',
            'last_name' => 'User',
            'status' => 'active',
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $employeeUser->id,
            'employee_code' => 'ORG-EMP-01',
            'first_name' => 'Employee',
            'last_name' => 'User',
            'status' => 'active',
        ]);

        return [$company, $branch, $hr, $managerUser, $employee, $hrEmployee, $managerEmployee];
    }
}
