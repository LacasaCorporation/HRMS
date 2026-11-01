<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RosterAssignment;
use App\Models\ShiftPattern;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShiftRosterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $to = Carbon::parse($filters['to'] ?? now()->startOfWeek()->addDays(6)->toDateString())->startOfDay();
        $from = Carbon::parse($filters['from'] ?? now()->startOfWeek()->toDateString())->startOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'to' => ['The end date must be on or after the start date.'],
            ]);
        }
        if ($from->diffInDays($to) > 30) {
            throw ValidationException::withMessages([
                'from' => ['Choose a date range of 31 days or fewer.'],
            ]);
        }

        $user = $request->user();
        $employees = PeopleScope::employees($user);
        $employeeIds = (clone $employees)->select('id');
        $assignments = RosterAssignment::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('shift_date', '>=', $from->toDateString())
            ->whereDate('shift_date', '<=', $to->toDateString())
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $query) use ($filters) {
                $term = '%'.trim($filters['search']).'%';
                $query->where(fn (Builder $search) => $search
                    ->whereHas('employee', fn (Builder $employee) => $employee
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('employee_code', 'like', $term)
                        ->orWhere('department', 'like', $term))
                    ->orWhereHas('shiftPattern', fn (Builder $shift) => $shift->where('name', 'like', $term)));
            })
            ->with([
                'employee:id,branch_id,employee_code,first_name,last_name,job_title,department',
                'employee.branch:id,name',
                'shiftPattern:id,branch_id,name,code,start_time,end_time,break_minutes',
                'shiftPattern.branch:id,name',
            ])
            ->orderBy('shift_date')
            ->orderBy('id')
            ->get();

        $patterns = ShiftPattern::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $branch) => $branch
                ->whereNull('branch_id')
                ->orWhere('branch_id', $user->branch_id)))
            ->with('branch:id,name')
            ->orderBy('start_time')
            ->orderBy('name')
            ->get();

        $visibleEmployees = (clone $employees)
            ->where('status', 'active')
            ->with('branch:id,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'job_title', 'department']);
        $branches = $user->company->branches()
            ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'assignments' => $assignments,
            'patterns' => $patterns,
            'employees' => $visibleEmployees,
            'branches' => $branches,
            'meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'assignments' => $assignments->count(),
                'scheduled_employees' => $assignments->pluck('employee_id')->unique()->count(),
                'active_patterns' => $patterns->where('status', 'active')->count(),
            ],
        ]);
    }

    public function storePattern(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('shift_patterns', 'code')->where('company_id', $user->company_id),
            ],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $user->company_id)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:240'],
        ]);
        $this->assertBranchScope($user, $data['branch_id'] ?? null);
        if ($data['start_time'] === $data['end_time']) {
            throw ValidationException::withMessages([
                'end_time' => ['End time must differ from start time.'],
            ]);
        }

        $pattern = ShiftPattern::create($data + [
            'company_id' => $user->company_id,
            'status' => 'active',
        ]);
        AuditTrail::record('roster.shift_pattern_created', $pattern);

        return response()->json(['pattern' => $pattern->load('branch:id,name')], 201);
    }

    public function updatePattern(Request $request, ShiftPattern $pattern): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($pattern->company_id === $user->company_id, 404);
        $this->assertBranchScope($user, $pattern->branch_id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        if ($data['status'] === 'inactive') {
            abort_if(
                $pattern->assignments()->whereDate('shift_date', '>=', today())->exists(),
                409,
                'This shift is assigned to an upcoming roster. Remove those assignments before archiving it.',
            );
        }

        $pattern->update($data);
        AuditTrail::record('roster.shift_pattern_status_changed', $pattern, ['status' => $data['status']]);

        return response()->json(['pattern' => $pattern->fresh()->load('branch:id,name')]);
    }

    public function storeAssignment(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'shift_pattern_id' => ['required', 'integer'],
            'shift_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($data, $user) {
            $employee = PeopleScope::employees($user)
                ->where('status', 'active')
                ->lockForUpdate()
                ->findOrFail($data['employee_id']);
            $pattern = ShiftPattern::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $branch) => $branch
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $user->branch_id)))
                ->findOrFail($data['shift_pattern_id']);

            abort_if(
                $pattern->branch_id && $employee->branch_id !== $pattern->branch_id,
                422,
                'The employee must belong to the shift pattern’s branch.',
            );
            abort_if(
                RosterAssignment::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('shift_date', $data['shift_date'])
                    ->exists(),
                409,
                'This employee already has a roster assignment on that date.',
            );

            $onApprovedLeave = DB::table('leave_requests')
                ->where('company_id', $user->company_id)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $data['shift_date'])
                ->whereDate('end_date', '>=', $data['shift_date'])
                ->exists();
            abort_if($onApprovedLeave, 409, 'This employee has approved leave on the selected date.');

            $assignment = RosterAssignment::create([
                'company_id' => $user->company_id,
                'employee_id' => $employee->id,
                'shift_pattern_id' => $pattern->id,
                'shift_date' => $data['shift_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);
            AuditTrail::record('roster.assignment_created', $assignment, [
                'employee_id' => $employee->id,
                'shift_pattern_id' => $pattern->id,
                'shift_date' => $data['shift_date'],
            ]);

            return response()->json([
                'assignment' => $assignment->load([
                    'employee:id,branch_id,employee_code,first_name,last_name,job_title,department',
                    'employee.branch:id,name',
                    'shiftPattern:id,branch_id,name,code,start_time,end_time,break_minutes',
                    'shiftPattern.branch:id,name',
                ]),
            ], 201);
        });
    }

    public function destroyAssignment(Request $request, RosterAssignment $assignment): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($assignment->company_id === $user->company_id, 404);
        abort_unless(PeopleScope::employees($user)->whereKey($assignment->employee_id)->exists(), 404);
        abort_if($assignment->shift_date->lt(today()), 409, 'Past roster assignments cannot be removed.');

        AuditTrail::record('roster.assignment_removed', $assignment, [
            'employee_id' => $assignment->employee_id,
            'shift_pattern_id' => $assignment->shift_pattern_id,
            'shift_date' => $assignment->shift_date->toDateString(),
        ]);
        $assignment->delete();

        return response()->json(['message' => 'Roster assignment removed.']);
    }

    private function ensurePeopleManager($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Only HR and administrators can manage shift patterns and rosters.');
    }

    private function assertBranchScope($user, ?int $branchId): void
    {
        abort_if($user->branch_id && $branchId && $user->branch_id !== $branchId, 403, 'This branch is outside your access scope.');
    }
}
