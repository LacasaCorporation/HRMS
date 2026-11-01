<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_token_can_be_refreshed_without_authentication(): void
    {
        $this->getJson('/api/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['csrf_token'])
            ->assertJsonPath('csrf_token', session()->token());
    }

    public function test_session_api_endpoints_auth_validation_errors_and_persisted_workflows(): void
    {
        $company = Company::create([
            'name' => 'Integration Company',
            'code' => 'INT',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Integration Branch',
            'code' => 'INT-01',
            'city' => 'Dubai',
        ]);

        $hr = User::create([
            'name' => 'Integration HR',
            'email' => 'hr@integration.example.test',
            'password' => 'integration-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $employeeUser = User::create([
            'name' => 'Integration Employee',
            'email' => 'employee@integration.example.test',
            'password' => 'integration-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'employee',
        ]);
        $hrEmployee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $hr->id,
            'employee_code' => 'INT-HR-01',
            'first_name' => 'Integration',
            'last_name' => 'HR',
            'status' => 'active',
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $employeeUser->id,
            'employee_code' => 'INT-EMP-01',
            'first_name' => 'Integration',
            'last_name' => 'Employee',
            'status' => 'active',
        ]);
        $leaveType = LeaveType::create([
            'company_id' => $company->id,
            'name' => 'Integration leave',
            'annual_entitlement' => 12,
            'is_paid' => true,
            'is_active' => true,
        ]);

        foreach ([
            ['GET', '/api/me'],
            ['GET', '/api/modules'],
            ['GET', '/api/dashboard'],
            ['GET', '/api/employees'],
            ['POST', '/api/employees'],
            ['GET', '/api/self-service'],
            ['PATCH', '/api/self-service/profile'],
            ['GET', '/api/organization'],
            ['POST', '/api/organization/departments'],
            ['PATCH', '/api/organization/departments/1'],
            ['POST', '/api/organization/positions'],
            ['POST', '/api/organization/positions/1/assign'],
            ['PATCH', '/api/organization/employees/1/reporting-line'],
            ['GET', '/api/recruitment'],
            ['POST', '/api/recruitment/requisitions'],
            ['PATCH', '/api/recruitment/requisitions/1/status'],
            ['POST', '/api/recruitment/requisitions/1/candidates'],
            ['PATCH', '/api/recruitment/candidates/1'],
            ['POST', '/api/recruitment/candidates/1/hire'],
            ['GET', '/api/attendance'],
            ['POST', '/api/attendance/check-in'],
            ['POST', '/api/attendance/1/check-out'],
            ['GET', '/api/attendance/report'],
            ['GET', '/api/shifts-rosters'],
            ['POST', '/api/shifts-rosters/patterns'],
            ['PATCH', '/api/shifts-rosters/patterns/1'],
            ['POST', '/api/shifts-rosters/assignments'],
            ['DELETE', '/api/shifts-rosters/assignments/1'],
            ['GET', '/api/payroll'],
            ['POST', '/api/payroll/cycles'],
            ['PATCH', '/api/payroll/items/1'],
            ['POST', '/api/payroll/cycles/1/submit'],
            ['POST', '/api/payroll/cycles/1/review'],
            ['GET', '/api/benefits'],
            ['POST', '/api/benefits/plans'],
            ['PATCH', '/api/benefits/plans/1'],
            ['POST', '/api/benefits/enrollments'],
            ['PATCH', '/api/benefits/enrollments/1'],
            ['POST', '/api/benefits/enrollments/1/submit'],
            ['POST', '/api/benefits/enrollments/1/review'],
            ['POST', '/api/benefits/enrollments/1/end'],
            ['GET', '/api/separations'],
            ['POST', '/api/separations/settlements'],
            ['PUT', '/api/separations/settlements/1/items'],
            ['POST', '/api/separations/settlements/1/submit'],
            ['POST', '/api/separations/settlements/1/review'],
            ['GET', '/api/performance'],
            ['POST', '/api/performance/cycles'],
            ['PATCH', '/api/performance/cycles/1/status'],
            ['PATCH', '/api/performance/reviews/1'],
            ['POST', '/api/performance/reviews/1/submit'],
            ['POST', '/api/performance/reviews/1/review'],
            ['GET', '/api/training'],
            ['POST', '/api/training/programs'],
            ['PATCH', '/api/training/programs/1'],
            ['POST', '/api/training/enrollments'],
            ['PATCH', '/api/training/enrollments/1'],
            ['GET', '/api/leave'],
            ['POST', '/api/leave'],
            ['POST', '/api/leave/1/review'],
            ['POST', '/api/logout'],
        ] as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }

        $this->postJson('/api/login', [])->assertUnprocessable();
        $this->postJson('/api/login', [
            'email' => $hr->email,
            'password' => 'incorrect',
        ])->assertUnprocessable();

        $this->postJson('/api/login', [
            'email' => $hr->email,
            'password' => 'integration-password',
        ])->assertOk()->assertJsonPath('user.role', 'hr');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.email', $hr->email);
        $this->getJson('/api/modules')
            ->assertOk()
            ->assertJsonCount(37, 'modules')
            ->assertJsonPath('modules.2.delivery', 'Foundation slice')
            ->assertJsonPath('modules.3.delivery', 'Foundation slice')
            ->assertJsonPath('modules.4.delivery', 'Foundation slice')
            ->assertJsonPath('modules.8.delivery', 'Foundation slice')
            ->assertJsonPath('modules.9.delivery', 'Foundation slice')
            ->assertJsonPath('modules.21.delivery', 'Foundation slice')
            ->assertJsonFragment(['slug' => 'shifts-rosters', 'delivery' => 'Foundation slice'])
            ->assertJsonFragment(['slug' => 'benefits', 'delivery' => 'Foundation slice'])
            ->assertJsonFragment(['slug' => 'separation-settlement', 'delivery' => 'Foundation slice'])
            ->assertJsonFragment(['slug' => 'performance', 'delivery' => 'Foundation slice'])
            ->assertJsonFragment(['slug' => 'training', 'delivery' => 'Foundation slice'])
            ->assertJsonFragment(['slug' => 'employee-self-service', 'delivery' => 'Foundation slice'])
            ->assertJsonFragment(['slug' => 'manager-self-service', 'delivery' => 'Foundation slice']);
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.employees', 2);
        $this->getJson('/api/employees')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/employees?search=INT-EMP-01')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/organization')
            ->assertOk()
            ->assertJsonCount(2, 'employees')
            ->assertJsonCount(0, 'departments')
            ->assertJsonCount(0, 'positions');
        $this->getJson('/api/recruitment')
            ->assertOk()
            ->assertJsonCount(0, 'requisitions')
            ->assertJsonCount(0, 'candidates')
            ->assertJsonPath('metrics.active_candidates', 0);
        $this->getJson('/api/attendance/report')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('metrics.entries', 0);
        $this->getJson('/api/shifts-rosters')
            ->assertOk()
            ->assertJsonCount(0, 'assignments')
            ->assertJsonCount(0, 'patterns');
        $this->getJson('/api/performance')
            ->assertOk()
            ->assertJsonCount(0, 'cycles')
            ->assertJsonCount(0, 'reviews')
            ->assertJsonCount(2, 'employees');
        $this->getJson('/api/training')
            ->assertOk()
            ->assertJsonCount(0, 'programs')
            ->assertJsonCount(0, 'enrollments')
            ->assertJsonCount(2, 'employees');

        $this->postJson('/api/employees', [
            'employee_code' => 'INT-INVALID',
        ])->assertUnprocessable();
        $this->postJson('/api/employees', [
            'employee_code' => 'INT-NEW-01',
            'first_name' => 'New',
            'last_name' => 'Record',
            'job_title' => 'Analyst',
            'department' => 'Finance',
            'employment_type' => 'Full-time',
            'join_date' => now()->toDateString(),
            'branch_id' => $branch->id,
        ])->assertCreated()->assertJsonPath('employee.employee_code', 'INT-NEW-01');

        $this->actingAs($employeeUser)
            ->getJson('/api/attendance')
            ->assertOk();
        $this->postJson('/api/attendance/check-in')
            ->assertCreated()
            ->assertJsonPath('attendance.employee_id', $employee->id);
        $this->postJson('/api/attendance/check-in')->assertStatus(409);
        $attendanceId = $this->getJson('/api/attendance')->json('0.id');
        $this->postJson("/api/attendance/{$attendanceId}/check-out")->assertOk();
        $this->postJson("/api/attendance/{$attendanceId}/check-out")->assertStatus(409);
        $this->postJson('/api/attendance/999999/check-out')->assertNotFound();

        $this->getJson('/api/leave')->assertOk();
        $this->postJson('/api/leave', [
            'leave_type_id' => 'not-an-id',
            'start_date' => 'not-a-date',
            'end_date' => 'not-a-date',
            'reason' => '',
        ])->assertUnprocessable();

        $start = Carbon::tomorrow();
        while ($start->isWeekend()) {
            $start->addDay();
        }
        $leavePayload = [
            'leave_type_id' => $leaveType->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->toDateString(),
            'reason' => 'API integration verification',
        ];
        $request = $this->postJson('/api/leave', $leavePayload)
            ->assertCreated()
            ->assertJsonPath('request.requested_days', '1.00');
        $requestId = $request->json('request.id');

        $this->postJson("/api/leave/{$requestId}/review", ['decision' => 'invalid'])
            ->assertUnprocessable();
        $this->postJson("/api/leave/{$requestId}/review", ['decision' => 'approved'])
            ->assertForbidden();

        $this->actingAs($hr);
        $this->postJson("/api/leave/{$requestId}/review", ['decision' => 'approved'])
            ->assertUnprocessable();

        LeaveBalance::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $start->year,
            'entitled_days' => 12,
            'used_days' => 0,
        ]);
        $this->postJson("/api/leave/{$requestId}/review", ['decision' => 'approved'])
            ->assertOk()
            ->assertJsonPath('request.status', 'approved');
        $this->postJson("/api/leave/{$requestId}/review", ['decision' => 'approved'])
            ->assertStatus(409);

        $this->postJson('/api/login', [
            'email' => $hr->email,
            'password' => 'integration-password',
        ])->assertOk();
        $this->postJson('/api/logout')->assertOk()->assertJsonStructure(['csrf_token']);
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
