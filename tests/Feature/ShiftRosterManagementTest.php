<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ShiftPattern;
use App\Models\User;
use App\Models\RosterAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftRosterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_create_patterns_and_assign_shifts_without_duplicate_days(): void
    {
        $this->seed();
        $hr = User::where('email', 'maya.hr@example.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'GTM-0003')->firstOrFail();
        $date = today()->addDay()->toDateString();

        $patternResponse = $this->actingAs($hr)->postJson('/api/shifts-rosters/patterns', [
            'name' => 'Evening shift',
            'code' => 'EVE-01',
            'branch_id' => $employee->branch_id,
            'start_time' => '20:00',
            'end_time' => '04:00',
            'break_minutes' => 30,
        ])->assertCreated()
            ->assertJsonPath('pattern.status', 'active');
        $patternId = $patternResponse->json('pattern.id');

        $this->postJson('/api/shifts-rosters/assignments', [
            'employee_id' => $employee->id,
            'shift_pattern_id' => $patternId,
            'shift_date' => $date,
            'notes' => 'Opening coverage handover',
        ])->assertCreated()
            ->assertJsonPath('assignment.employee.employee_code', 'GTM-0003')
            ->assertJsonPath('assignment.shift_pattern.name', 'Evening shift');

        $this->postJson('/api/shifts-rosters/assignments', [
            'employee_id' => $employee->id,
            'shift_pattern_id' => $patternId,
            'shift_date' => $date,
        ])->assertStatus(409);

        $this->getJson('/api/shifts-rosters?from='.$date.'&to='.$date)
            ->assertOk()
            ->assertJsonCount(1, 'assignments')
            ->assertJsonPath('meta.assignments', 1)
            ->assertJsonPath('meta.scheduled_employees', 1);
    }

    public function test_roster_visibility_and_mutations_are_role_and_branch_scoped(): void
    {
        $this->seed();
        $manager = User::where('email', 'omar.manager@example.test')->firstOrFail();
        $employeeUser = User::where('email', 'lina.employee@example.test')->firstOrFail();
        $hr = User::where('email', 'maya.hr@example.test')->firstOrFail();
        $company = $hr->company;
        $pattern = ShiftPattern::create([
            'company_id' => $company->id,
            'name' => 'Day shift',
            'code' => 'DAY-01',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'status' => 'active',
        ]);
        $date = today()->addDay()->toDateString();

        foreach (['GTM-0001', 'GTM-0002', 'GTM-0003', 'GTM-0005'] as $code) {
            $employee = Employee::where('employee_code', $code)->firstOrFail();
            RosterAssignment::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'shift_pattern_id' => $pattern->id,
                'shift_date' => $date,
            ]);
        }

        $this->actingAs($manager)->getJson('/api/shifts-rosters?from='.$date.'&to='.$date)
            ->assertOk()
            ->assertJsonCount(3, 'assignments')
            ->assertJsonMissing(['employee_code' => 'GTM-0001']);
        $this->actingAs($employeeUser)->getJson('/api/shifts-rosters?from='.$date.'&to='.$date)
            ->assertOk()
            ->assertJsonCount(1, 'assignments')
            ->assertJsonPath('assignments.0.employee.employee_code', 'GTM-0003');

        $this->actingAs($employeeUser)->postJson('/api/shifts-rosters/patterns', [
            'name' => 'Unauthorized',
            'code' => 'NO-ACCESS',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 0,
        ])->assertForbidden();

        $otherBranch = \App\Models\Branch::create([
            'company_id' => $company->id,
            'name' => 'Abu Dhabi Branch',
            'code' => 'AUH-01',
            'city' => 'Abu Dhabi',
        ]);
        $this->actingAs($hr)->postJson('/api/shifts-rosters/patterns', [
            'name' => 'Out of scope',
            'code' => 'OUT-01',
            'branch_id' => $otherBranch->id,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 0,
        ])->assertForbidden();
    }

    public function test_approved_leave_blocks_scheduling_and_future_shifts_cannot_be_archived(): void
    {
        $this->seed();
        $hr = User::where('email', 'maya.hr@example.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'GTM-0003')->firstOrFail();
        $leaveType = \App\Models\LeaveType::where('company_id', $hr->company_id)->firstOrFail();
        $date = today()->addDay()->toDateString();
        $pattern = ShiftPattern::create([
            'company_id' => $hr->company_id,
            'branch_id' => $employee->branch_id,
            'name' => 'Day shift',
            'code' => 'DAY-01',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'status' => 'active',
        ]);
        LeaveRequest::create([
            'company_id' => $hr->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $date,
            'end_date' => $date,
            'requested_days' => 1,
            'reason' => 'Approved day off',
            'status' => 'approved',
            'reviewed_by' => $hr->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($hr)->postJson('/api/shifts-rosters/assignments', [
            'employee_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'shift_date' => $date,
        ])->assertStatus(409)
            ->assertJsonPath('message', 'This employee has approved leave on the selected date.');

        RosterAssignment::create([
            'company_id' => $hr->company_id,
            'employee_id' => $employee->id,
            'shift_pattern_id' => $pattern->id,
            'shift_date' => $date,
        ]);
        $this->patchJson('/api/shifts-rosters/patterns/'.$pattern->id, ['status' => 'inactive'])
            ->assertStatus(409);
    }
}
