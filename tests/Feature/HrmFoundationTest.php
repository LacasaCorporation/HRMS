<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrmFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_catalog_contains_all_approved_core_and_advanced_entries(): void
    {
        $this->seed();
        $employee = User::where('email', 'lina.employee@example.test')->firstOrFail();

        $this->actingAs($employee)
            ->getJson('/api/modules')
            ->assertOk()
            ->assertJsonCount(37, 'modules');
    }

    public function test_session_login_and_company_scoped_dashboard_work(): void
    {
        $this->seed();

        $this->postJson('/api/login', [
            'email' => 'maya.hr@example.test',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.role', 'hr');

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.employees', 5)
            ->assertJsonPath('metrics.pending_approvals', 0);
    }

    public function test_employee_records_are_company_scoped_and_create_is_hr_only(): void
    {
        $this->seed();
        $employee = User::where('email', 'lina.employee@example.test')->firstOrFail();
        $foreignCompany = Company::create(['name' => 'Other Synthetic Co', 'code' => 'OTHER', 'country' => 'AE', 'currency' => 'AED']);
        Employee::create([
            'company_id' => $foreignCompany->id,
            'employee_code' => 'OTHER-001',
            'first_name' => 'Synthetic',
            'last_name' => 'Person',
            'status' => 'active',
        ]);

        $this->actingAs($employee)
            ->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['employee_code' => 'OTHER-001']);

        $this->postJson('/api/employees', [
            'employee_code' => 'GTM-0099',
            'first_name' => 'New',
            'last_name' => 'Person',
            'job_title' => 'Analyst',
            'department' => 'Finance',
            'employment_type' => 'Full-time',
            'join_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_approved_leave_updates_the_balance_and_cannot_be_reviewed_twice(): void
    {
        $this->seed();
        $employee = User::where('email', 'lina.employee@example.test')->firstOrFail();
        $leaveType = \App\Models\LeaveType::where('name', 'Annual leave')->firstOrFail();
        $start = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        $end = Carbon::parse($start)->addDay()->toDateString();

        $created = $this->actingAs($employee)
            ->postJson('/api/leave', [
                'leave_type_id' => $leaveType->id,
                'start_date' => $start,
                'end_date' => $end,
                'reason' => 'Synthetic demo request',
            ])
            ->assertCreated()
            ->assertJsonPath('request.requested_days', '2.00');

        $requestId = $created->json('request.id');
        $hr = User::where('email', 'maya.hr@example.test')->firstOrFail();

        $this->actingAs($hr)
            ->postJson("/api/leave/{$requestId}/review", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $employee->employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => Carbon::parse($start)->year,
            'used_days' => 2,
        ]);

        $this->postJson("/api/leave/{$requestId}/review", ['decision' => 'approved'])
            ->assertStatus(409);
    }

    public function test_attendance_check_in_and_check_out_are_persisted(): void
    {
        $this->seed();
        $employee = User::where('email', 'lina.employee@example.test')->firstOrFail();

        $checkIn = $this->actingAs($employee)
            ->postJson('/api/attendance/check-in')
            ->assertCreated()
            ->assertJsonPath('attendance.status', 'present');

        $entryId = $checkIn->json('attendance.id');
        $this->postJson('/api/attendance/check-in')->assertStatus(409);

        $this->postJson("/api/attendance/{$entryId}/check-out")
            ->assertOk()
            ->assertJsonPath('attendance.employee_id', $employee->employee->id);

        $this->assertDatabaseHas('attendance_entries', [
            'id' => $entryId,
            'employee_id' => $employee->employee->id,
            'status' => 'present',
        ]);
    }
}
