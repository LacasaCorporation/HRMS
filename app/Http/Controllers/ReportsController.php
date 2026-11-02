<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEntry;
use App\Models\LeaveRequest;
use App\Support\PeopleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $to = Carbon::parse($filters['to'] ?? today()->toDateString())->startOfDay();
        $from = Carbon::parse($filters['from'] ?? $to->copy()->subDays(29)->toDateString())->startOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'to' => ['The end date must be on or after the start date.'],
            ]);
        }

        if ($from->diffInDays($to) > 92) {
            throw ValidationException::withMessages([
                'from' => ['Choose a date range of 93 days or fewer.'],
            ]);
        }

        $user = $request->user();
        $employees = PeopleScope::employees($user);
        $employeeIds = (clone $employees)->select('id');
        $attendance = AttendanceEntry::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString());

        $daily = [];
        $attendanceCount = 0;
        $completed = 0;
        $working = 0;
        $minutes = 0;

        foreach ((clone $attendance)->select(['attendance_date', 'check_in', 'check_out'])->cursor() as $entry) {
            $date = $entry->attendance_date->toDateString();
            $daily[$date] ??= ['entries' => 0, 'completed' => 0, 'working' => 0, 'minutes' => 0];
            $daily[$date]['entries']++;
            $attendanceCount++;

            if ($entry->check_in && $entry->check_out) {
                $duration = max(0, Carbon::parse($entry->check_in)->diffInMinutes(Carbon::parse($entry->check_out), false));
                $completed++;
                $daily[$date]['completed']++;
                $daily[$date]['minutes'] += $duration;
                $minutes += $duration;
            } elseif ($entry->check_in) {
                $working++;
                $daily[$date]['working']++;
            }
        }

        $trend = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $day = $date->toDateString();
            $trend[] = [
                'date' => $day,
                'label' => $date->format('D, M j'),
                ...($daily[$day] ?? ['entries' => 0, 'completed' => 0, 'working' => 0, 'minutes' => 0]),
            ];
        }

        $leaveCounts = LeaveRequest::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $departmentCounts = (clone $employees)
            ->selectRaw("COALESCE(NULLIF(department, ''), 'Unassigned') as name, COUNT(*) as aggregate")
            ->groupBy('department')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->aggregate])
            ->values();

        $rows = (clone $attendance)
            ->with('employee:id,first_name,last_name,employee_code,department')
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in')
            ->limit(500)
            ->get()
            ->map(fn (AttendanceEntry $entry) => [
                'date' => $entry->attendance_date->toDateString(),
                'employee_code' => $entry->employee?->employee_code,
                'employee_name' => trim(($entry->employee?->first_name ?? '').' '.($entry->employee?->last_name ?? '')),
                'department' => $entry->employee?->department,
                'check_in' => $entry->check_in?->toDateTimeString(),
                'check_out' => $entry->check_out?->toDateTimeString(),
                'hours' => $entry->check_in && $entry->check_out
                    ? round(max(0, $entry->check_in->diffInMinutes($entry->check_out, false)) / 60, 2)
                    : null,
                'status' => $entry->check_out ? 'completed' : ($entry->check_in ? 'working' : $entry->status),
            ])
            ->values();

        $newHires = (clone $employees)
            ->whereNotNull('join_date')
            ->whereDate('join_date', '>=', $from->toDateString())
            ->whereDate('join_date', '<=', $to->toDateString())
            ->count();
        $totalEmployees = (clone $employees)->count();

        return response()->json([
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'attendance_total' => $attendanceCount,
                'attendance_limit' => 500,
                'attendance_truncated' => $attendanceCount > 500,
                'employees_total' => $totalEmployees,
                'leave_total' => (int) $leaveCounts->sum(),
            ],
            'metrics' => [
                'employees' => $totalEmployees,
                'active_employees' => (clone $employees)->where('status', 'active')->count(),
                'new_hires' => $newHires,
                'attendance_entries' => $attendanceCount,
                'completed_shifts' => $completed,
                'working_now' => $working,
                'hours_recorded' => round($minutes / 60, 2),
                'average_shift_hours' => $completed ? round($minutes / 60 / $completed, 2) : 0,
                'pending_leave' => (int) ($leaveCounts['pending'] ?? 0),
            ],
            'trend' => $trend,
            'departments' => $departmentCounts,
            'leave_status' => collect(['pending', 'approved', 'rejected'])
                ->map(fn (string $status) => ['status' => $status, 'count' => (int) ($leaveCounts[$status] ?? 0)])
                ->values(),
            'attendance_rows' => $rows,
        ]);
    }
}
