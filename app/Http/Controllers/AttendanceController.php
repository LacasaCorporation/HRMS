<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEntry;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employeeIds = PeopleScope::employees($request->user())->select('id');
        $query = AttendanceEntry::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->with('employee:id,first_name,last_name,employee_code,department')
            ->latest('attendance_date');

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->query('date'));
        }

        return response()->json($query->limit(60)->get());
    }

    public function report(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:working,completed'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);
        $to = Carbon::parse($filters['to'] ?? today()->toDateString())->startOfDay();
        $from = Carbon::parse($filters['from'] ?? $to->copy()->subDays(6)->toDateString())->startOfDay();

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
        $employeeIds = PeopleScope::employees($user)->select('id');
        $query = AttendanceEntry::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->when(($filters['status'] ?? null) === 'working', fn (Builder $builder) => $builder
                ->whereNotNull('check_in')
                ->whereNull('check_out'))
            ->when(($filters['status'] ?? null) === 'completed', fn (Builder $builder) => $builder
                ->whereNotNull('check_in')
                ->whereNotNull('check_out'))
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $builder) use ($filters) {
                $term = '%'.trim($filters['search']).'%';
                $builder->whereHas('employee', fn (Builder $employee) => $employee
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('employee_code', 'like', $term)
                    ->orWhere('department', 'like', $term));
            });

        $records = (clone $query)
            ->with('employee:id,branch_id,first_name,last_name,employee_code,department,job_title')
            ->with('employee.branch:id,name')
            ->orderByDesc('attendance_date')
            ->orderByDesc('check_in')
            ->paginate((int) ($filters['per_page'] ?? 50));

        $daily = [];
        $totalEntries = 0;
        $completed = 0;
        $working = 0;
        $totalMinutes = 0;
        foreach ((clone $query)->select(['attendance_date', 'check_in', 'check_out'])->cursor() as $entry) {
            $day = $entry->attendance_date->toDateString();
            $daily[$day] ??= ['entries' => 0, 'completed' => 0, 'working' => 0];
            $daily[$day]['entries']++;
            $totalEntries++;

            if ($entry->check_in && $entry->check_out) {
                $completed++;
                $daily[$day]['completed']++;
                $minutes = Carbon::parse($entry->check_in)->diffInMinutes(Carbon::parse($entry->check_out), false);
                $totalMinutes += max(0, $minutes);
            } elseif ($entry->check_in) {
                $working++;
                $daily[$day]['working']++;
            }
        }

        $trend = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $day = $date->toDateString();
            $trend[] = [
                'date' => $day,
                'label' => $date->format('D'),
                'entries' => $daily[$day]['entries'] ?? 0,
                'completed' => $daily[$day]['completed'] ?? 0,
                'working' => $daily[$day]['working'] ?? 0,
            ];
        }

        $todayEntry = $user->employee
            ? AttendanceEntry::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $user->employee->id)
                ->whereDate('attendance_date', today())
                ->first()
            : null;

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'metrics' => [
                'entries' => $totalEntries,
                'completed' => $completed,
                'working' => $working,
                'hours' => round($totalMinutes / 60, 2),
                'average_hours' => $completed ? round($totalMinutes / 60 / $completed, 2) : 0,
            ],
            'trend' => $trend,
            'today' => $todayEntry?->load('employee:id,first_name,last_name,employee_code'),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your sign-in is not linked to an employee record.');
        abort_unless($employee->status === 'active', 403, 'Inactive employees cannot record attendance.');
        abort_unless(
            $employee->company_id === $request->user()->company_id
            && (! $request->user()->branch_id || $employee->branch_id === $request->user()->branch_id),
            403,
        );

        $date = now()->toDateString();
        $entry = AttendanceEntry::query()
            ->where('company_id', $request->user()->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($entry) {
            abort_if($entry->check_in, 409, 'A check-in is already recorded for today.');
            $entry->update(['check_in' => now(), 'status' => 'present']);
        } else {
            $entry = AttendanceEntry::create([
                'company_id' => $request->user()->company_id,
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'check_in' => now(),
                'status' => 'present',
            ]);
        }

        AuditTrail::record('attendance.checked_in', $entry);

        return response()->json(['attendance' => $entry->load('employee:id,first_name,last_name,employee_code')], 201);
    }

    public function checkOut(Request $request, AttendanceEntry $entry): JsonResponse
    {
        abort_unless($entry->company_id === $request->user()->company_id, 404);
        abort_unless($entry->employee_id === $request->user()->employee?->id, 403);
        abort_if(! $entry->check_in, 409, 'Check in before checking out.');
        abort_if($entry->check_out, 409, 'A check-out is already recorded.');

        $entry->update(['check_out' => now()]);
        AuditTrail::record('attendance.checked_out', $entry);

        return response()->json(['attendance' => $entry->fresh()->load('employee:id,first_name,last_name,employee_code')]);
    }
}
