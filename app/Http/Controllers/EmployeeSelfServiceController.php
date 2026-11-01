<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEntry;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSelfServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $this->employeeFor($user);

        if (! $employee) {
            return response()->json([
                'employee' => null,
                'balances' => [],
                'leave_requests' => [],
                'leave_types' => [],
                'attendance' => [],
                'today_attendance' => null,
            ]);
        }

        $attendance = AttendanceEntry::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', today()->subDays(29))
            ->orderByDesc('attendance_date')
            ->limit(30)
            ->get(['id', 'attendance_date', 'check_in', 'check_out', 'status']);

        return response()->json([
            'employee' => $this->profileData($employee),
            'balances' => LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('year', now()->year)
                ->with('leaveType:id,name')
                ->orderBy('leave_type_id')
                ->get(['id', 'employee_id', 'leave_type_id', 'year', 'entitled_days', 'used_days']),
            'leave_requests' => LeaveRequest::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $employee->id)
                ->with('leaveType:id,name')
                ->latest()
                ->limit(20)
                ->get(),
            'leave_types' => LeaveType::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'annual_entitlement', 'is_paid']),
            'attendance' => $attendance,
            'today_attendance' => $attendance->first(fn (AttendanceEntry $entry) =>
                $entry->attendance_date->toDateString() === today()->toDateString()),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $this->employeeFor($user);
        abort_unless($employee && $employee->status === 'active', 403, 'An active employee profile linked to your account is required.');

        $data = $request->validate([
            'work_email' => [
                'nullable',
                'email',
                'max:190',
                Rule::unique('employees', 'work_email')
                    ->where('company_id', $user->company_id)
                    ->ignore($employee->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $employee->update($data);
        AuditTrail::record('employee.self_service_contact_updated', $employee, [
            'updated_fields' => array_keys($data),
        ]);

        return response()->json(['employee' => $this->profileData($employee->fresh())]);
    }

    private function employeeFor($user): ?Employee
    {
        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->when($user->branch_id, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->with(['branch:id,name', 'manager:id,first_name,last_name,job_title'])
            ->first();
    }

    private function profileData(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'work_email' => $employee->work_email,
            'phone' => $employee->phone,
            'job_title' => $employee->job_title,
            'department' => $employee->department,
            'employment_type' => $employee->employment_type,
            'status' => $employee->status,
            'join_date' => $employee->join_date?->toDateString(),
            'branch' => $employee->branch ? [
                'id' => $employee->branch->id,
                'name' => $employee->branch->name,
            ] : null,
            'manager' => $employee->manager ? [
                'id' => $employee->manager->id,
                'name' => trim($employee->manager->first_name.' '.$employee->manager->last_name),
                'job_title' => $employee->manager->job_title,
            ] : null,
        ];
    }
}
