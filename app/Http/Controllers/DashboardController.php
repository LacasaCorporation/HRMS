<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEntry;
use App\Models\LeaveRequest;
use App\Support\PeopleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $employees = PeopleScope::employees($user);
        $employeeIds = (clone $employees)->select('id');
        $today = now()->toDateString();

        $leaveRequests = LeaveRequest::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->with(['employee:id,first_name,last_name,employee_code', 'leaveType:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        $attendance = AttendanceEntry::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->with('employee:id,first_name,last_name,employee_code')
            ->get();

        return response()->json([
            'metrics' => [
                'employees' => (clone $employees)->where('status', 'active')->count(),
                'on_leave' => (clone $employees)->whereHas('leaveRequests', fn ($query) => $query
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today))->count(),
                'attendance_rate' => $employees->count() ? (int) round($attendance->where('status', 'present')->count() / $employees->count() * 100) : 0,
                'pending_approvals' => LeaveRequest::query()
                    ->where('company_id', $user->company_id)
                    ->whereIn('employee_id', $employeeIds)
                    ->where('status', 'pending')
                    ->count(),
            ],
            'attendance_today' => $attendance,
            'recent_leave' => $leaveRequests,
        ]);
    }
}
