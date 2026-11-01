<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LifecycleCase;
use App\Models\LifecycleTask;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LifecycleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);

        $cases = $this->casesFor($user)
            ->with([
                'employee:id,branch_id,employee_code,first_name,last_name,work_email,job_title,department,status,join_date',
                'employee.branch:id,name',
                'tasks',
            ])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('effective_date')
            ->orderByDesc('id')
            ->get();

        $activeCases = $this->casesFor($user)->where('status', 'in_progress');
        $taskScope = fn (Builder $query) => $query
            ->whereIn('lifecycle_case_id', (clone $activeCases)->select('id'))
            ->whereIn('status', ['pending', 'in_progress']);

        return response()->json([
            'cases' => $cases,
            'employees' => Employee::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'job_title', 'department', 'join_date']),
            'metrics' => [
                'active_onboarding' => (clone $activeCases)->where('type', 'onboarding')->count(),
                'active_offboarding' => (clone $activeCases)->where('type', 'offboarding')->count(),
                'overdue_tasks' => LifecycleTask::query()
                    ->where($taskScope)
                    ->whereDate('due_date', '<', today())
                    ->count(),
                'tasks_due_soon' => LifecycleTask::query()
                    ->where($taskScope)
                    ->whereBetween('due_date', [today(), today()->addDays(7)])
                    ->count(),
                'completed_this_month' => $this->casesFor($user)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['onboarding', 'offboarding'])],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($data, $user) {
            $employee = Employee::query()
                ->where('company_id', $user->company_id)
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->lockForUpdate()
                ->findOrFail($data['employee_id']);

            abort_unless($employee->status === 'active', 422, 'Only active employees can start a lifecycle workflow.');

            if ($this->casesFor($user)->where('employee_id', $employee->id)->where('type', $data['type'])->exists()) {
                throw ValidationException::withMessages([
                    'employee_id' => ['A workflow of this type already exists for this employee.'],
                ]);
            }

            $case = LifecycleCase::create([
                'company_id' => $user->company_id,
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'created_by' => $user->id,
                'type' => $data['type'],
                'status' => 'in_progress',
                'effective_date' => $data['effective_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($this->taskTemplate($data['type']) as [$title, $ownerTeam, $daysFromEffectiveDate]) {
                $case->tasks()->create([
                    'title' => $title,
                    'owner_team' => $ownerTeam,
                    'due_date' => Carbon::parse($data['effective_date'])->addDays($daysFromEffectiveDate),
                ]);
            }

            AuditTrail::record('lifecycle.case_started', $case, [
                'type' => $case->type,
                'employee_id' => $employee->id,
            ]);

            return response()->json([
                'case' => $case->load([
                    'employee:id,branch_id,employee_code,first_name,last_name,work_email,job_title,department,status,join_date',
                    'employee.branch:id,name',
                    'tasks',
                ]),
            ], 201);
        });
    }

    public function updateTask(Request $request, LifecycleTask $task): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        $case = $task->lifecycleCase;
        abort_unless($this->casesFor($user)->whereKey($case->id)->exists(), 404);
        abort_unless($case->status === 'in_progress', 409, 'Completed workflows cannot be changed.');

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $task->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'completed_at' => $data['status'] === 'completed' ? ($task->completed_at ?? now()) : null,
        ]);

        AuditTrail::record('lifecycle.task_updated', $task, [
            'case_id' => $case->id,
            'status' => $task->status,
        ]);

        return response()->json(['task' => $task->fresh()]);
    }

    public function complete(Request $request, LifecycleCase $case): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($this->casesFor($user)->whereKey($case->id)->exists(), 404);

        $data = $request->validate([
            'confirm_deactivation' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($case, $data, $user) {
            $lockedCase = LifecycleCase::query()->lockForUpdate()->findOrFail($case->id);
            abort_unless($lockedCase->status === 'in_progress', 409, 'This workflow is already closed.');

            if ($lockedCase->tasks()->where('status', '!=', 'completed')->exists()) {
                throw ValidationException::withMessages([
                    'tasks' => ['Complete every checklist item before closing this workflow.'],
                ]);
            }

            if ($lockedCase->type === 'offboarding') {
                abort_unless(($data['confirm_deactivation'] ?? false) === true, 422, 'Confirm employee deactivation to close offboarding.');
                $employee = Employee::query()->lockForUpdate()->findOrFail($lockedCase->employee_id);
                abort_unless($employee->status === 'active', 409, 'This employee is no longer active.');
                $employee->update(['status' => 'inactive']);
            }

            $lockedCase->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            AuditTrail::record('lifecycle.case_completed', $lockedCase, [
                'type' => $lockedCase->type,
                'employee_id' => $lockedCase->employee_id,
            ]);

            return response()->json([
                'case' => $lockedCase->fresh()->load([
                    'employee:id,branch_id,employee_code,first_name,last_name,work_email,job_title,department,status,join_date',
                    'employee.branch:id,name',
                    'tasks',
                ]),
            ]);
        });
    }

    private function casesFor($user): Builder
    {
        return LifecycleCase::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function ensurePeopleManager($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Lifecycle workflows are restricted to HR and administrators.');
    }

    private function taskTemplate(string $type): array
    {
        if ($type === 'onboarding') {
            return [
                ['Confirm offer, contract and required paperwork', 'People Operations', -7],
                ['Prepare workstation, equipment and access requests', 'IT & Facilities', -3],
                ['Confirm payroll and benefits setup', 'HR Operations', -2],
                ['Share first-week plan with the manager', 'People Manager', -1],
                ['Complete orientation and policy walkthrough', 'People Operations', 1],
                ['Schedule the 30-day manager check-in', 'People Manager', 30],
            ];
        }

        return [
            ['Confirm handover plan and knowledge transfer', 'People Manager', -5],
            ['Complete exit interview and record feedback', 'People Operations', -1],
            ['Collect company assets and confirm return', 'IT & Facilities', 0],
            ['Revoke system access at the approved exit time', 'IT & Security', 0],
            ['Submit final payroll inputs to the payroll owner', 'HR Operations', 1],
            ['Record outstanding clearances and handover status', 'People Operations', 2],
        ];
    }
}
