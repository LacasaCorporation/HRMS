<?php

namespace Tests\Feature;

use App\Models\AttendanceEntry;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_filters_attendance_and_returns_daily_worked_hours(): void
    {
        $this->seed();
        $hr = User::where('email', 'maya.hr@example.test')->firstOrFail();
        $employee = Employee::where('employee_code', 'GTM-0003')->firstOrFail();
        $today = today();
        $yesterday = today()->subDay();

        AttendanceEntry::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $today->toDateString(),
            'check_in' => $today->copy()->setTime(8, 0),
            'status' => 'present',
        ]);
        AttendanceEntry::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'attendance_date' => $yesterday->toDateString(),
            'check_in' => $yesterday->copy()->setTime(8, 0),
            'check_out' => $yesterday->copy()->setTime(16, 30),
            'status' => 'present',
        ]);

        $this->actingAs($hr)->getJson('/api/attendance/report?from='.$yesterday->toDateString().'&to='.$today->toDateString())
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('metrics.entries', 2)
            ->assertJsonPath('metrics.working', 1)
            ->assertJsonPath('metrics.completed', 1)
            ->assertJsonPath('metrics.hours', 8.5)
            ->assertJsonPath('trend.0.entries', 1)
            ->assertJsonPath('trend.1.working', 1)
            ->assertJsonPath('today', null);

        $this->getJson('/api/attendance/report?from='.$yesterday->toDateString().'&to='.$today->toDateString().'&status=working&search=GTM-0003')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('metrics.entries', 1)
            ->assertJsonPath('metrics.working', 1)
            ->assertJsonPath('data.0.employee.employee_code', 'GTM-0003');

        $this->getJson('/api/attendance/report?from='.$today->toDateString().'&to='.$yesterday->toDateString())
            ->assertUnprocessable();
        $this->getJson('/api/attendance/report?from='.today()->subDays(100)->toDateString().'&to='.$today->toDateString())
            ->assertUnprocessable();
    }

    public function test_report_respects_manager_scope_and_inactive_employees_cannot_punch(): void
    {
        $this->seed();
        $manager = User::where('email', 'omar.manager@example.test')->firstOrFail();
        $company = Company::where('code', 'GULF-TRD')->firstOrFail();
        $employees = Employee::where('company_id', $company->id)->get();

        foreach ($employees as $employee) {
            AttendanceEntry::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'attendance_date' => today()->toDateString(),
                'check_in' => now()->subHours(2),
                'check_out' => now(),
                'status' => 'present',
            ]);
        }

        $this->actingAs($manager)->getJson('/api/attendance/report')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonMissing(['employee_code' => 'GTM-0001']);

        $employee = Employee::where('employee_code', 'GTM-0003')->firstOrFail();
        $user = User::where('email', 'lina.employee@example.test')->firstOrFail();
        $employee->update(['status' => 'inactive']);
        $this->actingAs($user)->postJson('/api/attendance/check-in')->assertForbidden();
    }
}
