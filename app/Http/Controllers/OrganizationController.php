<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $employeeQuery = PeopleScope::employees($user);
        $employees = (clone $employeeQuery)
            ->with([
                'branch:id,name',
                'departmentRecord:id,name,code',
                'position:id,title,code',
                'manager:id,first_name,last_name,job_title',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'id', 'company_id', 'branch_id', 'department_id', 'position_id', 'manager_id',
                'employee_code', 'first_name', 'last_name', 'work_email', 'job_title',
                'department', 'status',
            ]);

        $departments = Department::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $branchQuery) => $branchQuery
                ->whereNull('branch_id')
                ->orWhere('branch_id', $user->branch_id)))
            ->with([
                'branch:id,name',
                'parent:id,name',
                'manager:id,first_name,last_name,job_title',
            ])
            ->orderBy('name')
            ->get();

        $employeeCounts = $employees->whereNotNull('department_id')->countBy('department_id');
        $departments->each(fn (Department $department) => $department->setAttribute(
            'employees_count',
            $employeeCounts->get($department->id, 0),
        ));

        $positions = Position::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $branchQuery) => $branchQuery
                ->whereNull('branch_id')
                ->orWhere('branch_id', $user->branch_id)))
            ->with(['department:id,name,code', 'branch:id,name', 'reportsTo:id,title,code'])
            ->orderBy('title')
            ->get();

        $filledCounts = $employees->whereNotNull('position_id')->countBy('position_id');
        $positions->each(fn (Position $position) => $position->setAttribute(
            'filled_count',
            $filledCounts->get($position->id, 0),
        ));

        $branches = $user->company
            ->branches()
            ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'city']);

        return response()->json([
            'company' => $user->company->only(['id', 'name', 'code', 'country', 'currency']),
            'branches' => $branches,
            'departments' => $departments,
            'positions' => $positions,
            'employees' => $employees,
            'metrics' => [
                'employees' => $employees->where('status', 'active')->count(),
                'departments' => $departments->where('status', 'active')->count(),
                'branches' => $branches->count(),
                'open_positions' => $positions->sum(fn (Position $position) => $position->status === 'open'
                    ? max(0, $position->headcount - $position->filled_count)
                    : 0),
                'planned_headcount' => $positions->sum('headcount'),
            ],
        ]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);

        $data = $request->validate($this->departmentRules($user));
        $this->assertBranchScope($user, $data['branch_id'] ?? null);
        $this->assertDepartmentHierarchy($data['parent_id'] ?? null, $data['branch_id'] ?? null);
        $this->assertDepartmentManager($user, $data['manager_employee_id'] ?? null, $data['branch_id'] ?? null);

        $department = Department::create($data + [
            'company_id' => $user->company_id,
            'status' => 'active',
        ]);
        AuditTrail::record('organization.department_created', $department);

        return response()->json(['department' => $department->load(['branch:id,name', 'parent:id,name'])], 201);
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($department->company_id === $user->company_id, 404);
        $this->assertBranchScope($user, $department->branch_id);

        $data = $request->validate($this->departmentRules($user, $department));
        $branchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : $department->branch_id;
        $parentId = array_key_exists('parent_id', $data) ? $data['parent_id'] : $department->parent_id;
        $managerId = array_key_exists('manager_employee_id', $data)
            ? $data['manager_employee_id']
            : $department->manager_employee_id;
        $this->assertBranchScope($user, $branchId);
        $this->assertDepartmentHierarchy($parentId, $branchId, $department->id);
        $this->assertDepartmentManager($user, $managerId, $branchId);

        $department->update($data);
        AuditTrail::record('organization.department_updated', $department, ['fields' => array_keys($data)]);

        return response()->json(['department' => $department->fresh()->load(['branch:id,name', 'parent:id,name'])]);
    }

    public function storePosition(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        $data = $request->validate($this->positionRules($user));
        $this->assertBranchScope($user, $data['branch_id'] ?? null);
        $this->assertPositionDepartment($user, $data['department_id'], $data['branch_id'] ?? null);
        $this->assertReportsToPosition($user, $data['reports_to_position_id'] ?? null);

        $position = Position::create($data + [
            'company_id' => $user->company_id,
            'status' => 'open',
        ]);
        AuditTrail::record('organization.position_created', $position);

        return response()->json(['position' => $position->load(['department:id,name', 'branch:id,name'])], 201);
    }

    public function assignPosition(Request $request, Position $position): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($position->company_id === $user->company_id, 404);
        $this->assertBranchScope($user, $position->branch_id);
        abort_unless($position->status === 'open', 409, 'Only open positions can be assigned.');

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('company_id', $user->company_id)],
        ]);
        $employee = PeopleScope::employees($user)->whereKey($data['employee_id'])->firstOrFail();
        abort_if($position->branch_id && $employee->branch_id !== $position->branch_id, 422, 'The employee must belong to this position’s branch.');

        return DB::transaction(function () use ($position, $employee) {
            $lockedPosition = Position::query()->lockForUpdate()->findOrFail($position->id);
            abort_unless($lockedPosition->status === 'open', 409, 'Only open positions can be assigned.');
            abort_if(
                Employee::query()->where('position_id', $lockedPosition->id)->count() >= $lockedPosition->headcount,
                409,
                'This position has reached its planned headcount.',
            );

            $employee->update([
                'position_id' => $lockedPosition->id,
                'department_id' => $lockedPosition->department_id,
                'department' => $lockedPosition->department()->value('name'),
                'job_title' => $lockedPosition->title,
            ]);
            AuditTrail::record('organization.position_assigned', $lockedPosition, ['employee_id' => $employee->id]);

            return response()->json([
                'position' => $lockedPosition->fresh()->load(['department:id,name', 'branch:id,name']),
                'employee' => $employee->fresh()->load(['departmentRecord:id,name', 'position:id,title']),
            ]);
        });
    }

    public function updateReportingLine(Request $request, Employee $employee): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($employee->company_id === $user->company_id, 404);
        abort_unless(PeopleScope::employees($user)->whereKey($employee->id)->exists(), 404);

        $data = $request->validate([
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('company_id', $user->company_id),
            ],
        ]);
        $managerId = $data['manager_id'] ?? null;
        if ($managerId !== null) {
            $manager = PeopleScope::employees($user)->whereKey($managerId)->firstOrFail();
            abort_if($manager->branch_id !== $employee->branch_id, 422, 'Reporting lines must stay within the same branch.');
            $this->assertNoReportingCycle($employee, $manager);
        }

        $employee->update(['manager_id' => $managerId]);
        AuditTrail::record('organization.reporting_line_updated', $employee, ['manager_id' => $managerId]);

        return response()->json([
            'employee' => $employee->fresh()->load(['manager:id,first_name,last_name,job_title']),
        ]);
    }

    private function departmentRules($user, ?Department $department = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('departments', 'code')
                    ->where('company_id', $user->company_id)
                    ->ignore($department?->id),
            ],
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('company_id', $user->company_id),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('departments', 'id')->where('company_id', $user->company_id),
            ],
            'manager_employee_id' => [
                'nullable', 'integer',
                Rule::exists('employees', 'id')->where('company_id', $user->company_id),
            ],
            'cost_center' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    private function positionRules($user): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('positions', 'code')->where('company_id', $user->company_id),
            ],
            'department_id' => [
                'required', 'integer',
                Rule::exists('departments', 'id')->where('company_id', $user->company_id),
            ],
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('company_id', $user->company_id),
            ],
            'reports_to_position_id' => [
                'nullable', 'integer',
                Rule::exists('positions', 'id')->where('company_id', $user->company_id),
            ],
            'grade' => ['nullable', 'string', 'max:40'],
            'employment_type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Temporary'])],
            'headcount' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }

    private function ensurePeopleManager($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Only HR and administrators can change organization records.');
    }

    private function assertBranchScope($user, ?int $branchId): void
    {
        if ($user->branch_id && $branchId && $user->branch_id !== $branchId) {
            abort(403, 'This branch is outside your organization scope.');
        }
    }

    private function assertDepartmentManager($user, ?int $managerId, ?int $branchId): void
    {
        if ($managerId === null) {
            return;
        }

        $manager = PeopleScope::employees($user)->whereKey($managerId)->firstOrFail();
        if ($branchId && $manager->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'manager_employee_id' => ['Choose a manager from the selected branch.'],
            ]);
        }
    }

    private function assertDepartmentHierarchy(?int $parentId, ?int $branchId, ?int $departmentId = null): void
    {
        if ($parentId === null) {
            return;
        }

        $visited = [];
        $cursorId = $parentId;
        while ($cursorId !== null) {
            if ($cursorId === $departmentId || isset($visited[$cursorId])) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A department cannot be placed beneath itself or one of its descendants.'],
                ]);
            }
            $visited[$cursorId] = true;
            $parent = Department::query()->findOrFail($cursorId);
            if ($branchId && $parent->branch_id && $parent->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A department can only inherit from a company-wide department or one in the same branch.'],
                ]);
            }
            $cursorId = $parent->parent_id;
        }
    }

    private function assertPositionDepartment($user, int $departmentId, ?int $branchId): void
    {
        $department = Department::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($departmentId);
        if ($branchId && $department->branch_id && $department->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'department_id' => ['Choose a department from the selected branch.'],
            ]);
        }
    }

    private function assertReportsToPosition($user, ?int $positionId): void
    {
        if ($positionId !== null) {
            Position::query()->where('company_id', $user->company_id)->findOrFail($positionId);
        }
    }

    private function assertNoReportingCycle(Employee $employee, Employee $manager): void
    {
        $visited = [];
        $cursor = $manager;
        while ($cursor) {
            if ($cursor->id === $employee->id || isset($visited[$cursor->id])) {
                throw ValidationException::withMessages([
                    'manager_id' => ['This reporting line would create a circular hierarchy.'],
                ]);
            }
            $visited[$cursor->id] = true;
            $cursor = $cursor->manager_id
                ? Employee::query()->where('company_id', $employee->company_id)->find($cursor->manager_id)
                : null;
        }
    }
}
