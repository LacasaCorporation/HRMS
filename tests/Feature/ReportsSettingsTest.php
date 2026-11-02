<?php

namespace Tests\Feature;

use App\Models\AttendanceEntry;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_summarize_only_records_inside_the_signed_in_users_scope(): void
    {
        [$company, $branch] = $this->companyAndBranch('SCOPE');
        [$hr] = $this->person($company, $branch, 'reports-hr@test.example', 'hr');
        [, $employee] = $this->person($company, $branch, 'reports-owner@test.example', 'employee');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other branch',
            'code' => 'SCOPE-OTHER',
        ]);
        [, $otherEmployee] = $this->person($company, $otherBranch, 'reports-other@test.example', 'employee');
        $type = LeaveType::create([
            'company_id' => $company->id,
            'name' => 'Annual',
            'annual_entitlement' => 20,
            'is_paid' => true,
            'is_active' => true,
        ]);

        AttendanceEntry::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'attendance_date' => today()->toDateString(),
            'check_in' => today()->setTime(9, 0),
            'check_out' => today()->setTime(17, 0),
            'status' => 'present',
        ]);
        AttendanceEntry::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'attendance_date' => today()->toDateString(),
            'check_in' => today()->setTime(9, 0),
            'status' => 'present',
        ]);
        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'requested_days' => 2,
            'reason' => 'Personal leave',
            'status' => 'pending',
        ]);

        $this->actingAs($hr)->getJson('/api/reports?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('metrics.employees', 2)
            ->assertJsonPath('metrics.active_employees', 2)
            ->assertJsonPath('metrics.attendance_entries', 1)
            ->assertJsonPath('metrics.completed_shifts', 1)
            ->assertJsonPath('metrics.hours_recorded', 8)
            ->assertJsonPath('metrics.pending_leave', 1)
            ->assertJsonPath('meta.attendance_total', 1)
            ->assertJsonPath('attendance_rows.0.employee_code', $employee->employee_code);

        $this->actingAs($employee->user)->getJson('/api/reports')
            ->assertOk()
            ->assertJsonPath('metrics.employees', 1)
            ->assertJsonPath('metrics.attendance_entries', 1)
            ->assertJsonPath('metrics.pending_leave', 1);
    }

    public function test_reports_reject_invalid_or_oversized_date_ranges(): void
    {
        [$company, $branch] = $this->companyAndBranch('DATES');
        [$hr] = $this->person($company, $branch, 'reports-dates@test.example', 'hr');

        $this->actingAs($hr)->getJson('/api/reports?from=2026-01-01&to=2026-04-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
        $this->getJson('/api/reports?from=2026-11-02&to=2026-11-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    public function test_hr_can_manage_company_and_leave_policy_settings_with_audit_events(): void
    {
        [$company, $branch] = $this->companyAndBranch('SETTINGS');
        [$hr] = $this->person($company, $branch, 'settings-hr@test.example', 'hr');

        $this->actingAs($hr)->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('company.name', 'Settings Company')
            ->assertJsonPath('company.branches.0.code', 'SETTINGS-MAIN');

        $this->patchJson('/api/settings/company', [
            'name' => 'Northstar Trading',
            'country' => 'AE',
            'currency' => 'AED',
        ])->assertOk()->assertJsonPath('company.name', 'Northstar Trading');

        $created = $this->postJson('/api/settings/leave-types', [
            'name' => 'Compassionate',
            'annual_entitlement' => 5,
            'is_paid' => true,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('leave_type.name', 'Compassionate')
            ->assertJsonPath('leave_type.annual_entitlement', '5.00');
        $leaveTypeId = $created->json('leave_type.id');

        $this->patchJson("/api/settings/leave-types/{$leaveTypeId}", [
            'name' => 'Compassionate leave',
            'annual_entitlement' => 7,
            'is_paid' => false,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('leave_type.name', 'Compassionate leave')
            ->assertJsonPath('leave_type.annual_entitlement', '7.00')
            ->assertJsonPath('leave_type.is_paid', false)
            ->assertJsonPath('leave_type.is_active', false);

        $this->assertDatabaseHas('audit_events', ['event' => 'settings.company_updated']);
        $this->assertDatabaseHas('audit_events', ['event' => 'settings.leave_type_created']);
        $this->assertDatabaseHas('audit_events', ['event' => 'settings.leave_type_updated']);
    }

    public function test_settings_are_role_and_company_scoped(): void
    {
        [$company, $branch] = $this->companyAndBranch('ACCESS');
        [$employeeUser] = $this->person($company, $branch, 'settings-employee@test.example', 'employee');
        [$otherCompany, $otherBranch] = $this->companyAndBranch('OUTSIDE');
        [$otherHr] = $this->person($otherCompany, $otherBranch, 'settings-other-hr@test.example', 'hr');
        $otherType = LeaveType::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other leave',
            'annual_entitlement' => 5,
            'is_paid' => true,
            'is_active' => true,
        ]);
        $companyType = LeaveType::create([
            'company_id' => $company->id,
            'name' => 'Company leave',
            'annual_entitlement' => 10,
            'is_paid' => true,
            'is_active' => true,
        ]);

        $this->actingAs($employeeUser)->getJson('/api/settings')->assertForbidden();
        $this->patchJson('/api/settings/company', [
            'name' => 'Unauthorized update',
            'country' => 'AE',
            'currency' => 'AED',
        ])->assertForbidden();
        $this->actingAs($otherHr)->patchJson('/api/settings/leave-types/'.$companyType->id, [
            'name' => 'Changed outside',
            'annual_entitlement' => 3,
            'is_paid' => false,
            'is_active' => true,
        ])->assertNotFound();
        $this->assertDatabaseHas('leave_types', ['id' => $otherType->id, 'name' => 'Other leave']);
    }

    private function companyAndBranch(string $code): array
    {
        $company = Company::create([
            'name' => ucfirst(strtolower($code)).' Company',
            'code' => $code,
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Site',
            'code' => $code.'-MAIN',
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
            'join_date' => today()->toDateString(),
        ]);

        return [$user, $employee];
    }
}
