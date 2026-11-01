<?php

namespace Tests\Feature;

use App\Models\AttendanceEntry;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_returns_only_the_signed_in_employees_personal_records(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$user, $employee] = $this->employee($company, $branch, 'ESS-001', 'Avery', 'Employee');
        [, $otherEmployee] = $this->employee($company, $branch, 'ESS-002', 'Other', 'Employee');
        $leaveType = LeaveType::create([
            'company_id' => $company->id,
            'name' => 'Annual leave',
            'annual_entitlement' => 24,
            'is_paid' => true,
            'is_active' => true,
        ]);
        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'entitled_days' => 24,
            'used_days' => 4,
        ]);
        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addWeekdays(1),
            'end_date' => today()->addWeekdays(1),
            'requested_days' => 1,
            'reason' => 'Personal appointment',
        ]);
        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => today()->addWeekdays(2),
            'end_date' => today()->addWeekdays(2),
            'requested_days' => 1,
            'reason' => 'Other employee private reason',
        ]);
        AttendanceEntry::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'attendance_date' => today(),
            'check_in' => now(),
            'status' => 'present',
        ]);
        AttendanceEntry::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'attendance_date' => today(),
            'check_in' => now(),
            'status' => 'present',
        ]);

        $this->actingAs($user)->getJson('/api/self-service')
            ->assertOk()
            ->assertJsonPath('employee.employee_code', 'ESS-001')
            ->assertJsonPath('employee.branch.name', $branch->name)
            ->assertJsonMissingPath('employee.monthly_salary')
            ->assertJsonCount(1, 'balances')
            ->assertJsonCount(1, 'leave_requests')
            ->assertJsonCount(1, 'attendance')
            ->assertJsonPath('leave_requests.0.reason', 'Personal appointment')
            ->assertJsonStructure(['today_attendance' => ['id', 'check_in']]);
    }

    public function test_employee_can_update_only_their_contact_details(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$user, $employee] = $this->employee($company, $branch, 'ESS-001', 'Avery', 'Employee');
        $this->employee($company, $branch, 'ESS-002', 'Other', 'Employee', 'taken@example.test');

        $this->actingAs($user)->patchJson('/api/self-service/profile', [
            'work_email' => 'avery.updated@example.test',
            'phone' => '+971500000001',
            'job_title' => 'Unauthorized title change',
        ])->assertOk()
            ->assertJsonPath('employee.work_email', 'avery.updated@example.test')
            ->assertJsonPath('employee.phone', '+971500000001');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'work_email' => 'avery.updated@example.test',
            'phone' => '+971500000001',
            'job_title' => null,
        ]);

        $this->patchJson('/api/self-service/profile', [
            'work_email' => 'taken@example.test',
            'phone' => '+971500000002',
        ])->assertUnprocessable();
    }

    public function test_unlinked_employees_cannot_change_a_profile_and_routes_require_authentication(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        $user = User::create([
            'name' => 'Unlinked Employee',
            'email' => 'unlinked@example.test',
            'password' => 'test-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'employee',
        ]);

        $this->getJson('/api/self-service')
            ->assertUnauthorized();

        $this->actingAs($user)->getJson('/api/self-service')
            ->assertOk()
            ->assertJsonPath('employee', null)
            ->assertJsonCount(0, 'leave_requests');

        $this->patchJson('/api/self-service/profile', [
            'work_email' => 'unlinked@example.test',
            'phone' => '+971500000000',
        ])->assertForbidden();
    }

    private function companyAndBranch(): array
    {
        $company = Company::create([
            'name' => 'Self Service Company',
            'code' => 'ESS',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'ESS-01',
            'city' => 'Dubai',
        ]);

        return [$company, $branch];
    }

    private function employee(Company $company, Branch $branch, string $code, string $firstName, string $lastName, ?string $email = null): array
    {
        $user = User::create([
            'name' => "{$firstName} {$lastName}",
            'email' => strtolower($code).'@example.test',
            'password' => 'test-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'employee',
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'employee_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'work_email' => $email,
            'monthly_salary' => 12000,
            'status' => 'active',
        ]);

        return [$user, $employee];
    }
}
