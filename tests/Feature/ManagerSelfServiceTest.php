<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_workspace_data_and_leave_reviews_are_limited_to_direct_reports(): void
    {
        $this->seed();
        $manager = User::where('email', 'omar.manager@example.test')->firstOrFail();
        $companyId = $manager->company_id;
        $directReport = Employee::where('employee_code', 'GTM-0003')->firstOrFail();
        $unrelatedEmployee = Employee::where('employee_code', 'GTM-0001')->firstOrFail();
        $leaveType = LeaveType::where('company_id', $companyId)->where('name', 'Annual leave')->firstOrFail();
        $date = Carbon::tomorrow();
        while ($date->isWeekend()) {
            $date->addDay();
        }

        $directReportRequest = $this->createLeaveRequest($companyId, $directReport, $leaveType, $date);
        $unrelatedRequest = $this->createLeaveRequest($companyId, $unrelatedEmployee, $leaveType, $date);

        $this->actingAs($manager)->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonMissing(['employee_code' => 'GTM-0001']);

        $this->getJson('/api/leave')
            ->assertOk()
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.employee.employee_code', $directReport->employee_code);

        $this->getJson('/api/attendance/report')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->postJson("/api/leave/{$directReportRequest->id}/review", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');

        $this->postJson("/api/leave/{$unrelatedRequest->id}/review", ['decision' => 'rejected'])
            ->assertForbidden();
    }

    private function createLeaveRequest(int $companyId, Employee $employee, LeaveType $leaveType, Carbon $date): LeaveRequest
    {
        return LeaveRequest::create([
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'requested_days' => 1,
            'reason' => 'Synthetic manager scope test',
        ]);
    }
}
