<?php

namespace App\Http\Controllers;

use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DisciplinaryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $scope = $this->casesFor($user);
        $cases = (clone $scope)
            ->with([
                'employee:id,branch_id,employee_code,first_name,last_name,department,status',
                'creator:id,name',
                'investigator:id,name',
                'resolver:id,name',
            ])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'under_review' THEN 1 ELSE 2 END")
            ->orderByDesc('incident_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $total = (clone $scope)->count();

        return response()->json([
            'cases' => $cases,
            'employees' => Employee::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'department']),
            'investigators' => User::query()
                ->where('company_id', $user->company_id)
                ->whereIn('role', ['hr', 'admin'])
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'meta' => [
                'total' => $total,
                'open' => (clone $scope)->where('status', 'open')->count(),
                'under_review' => (clone $scope)->where('status', 'under_review')->count(),
                'resolved' => (clone $scope)->where('status', 'resolved')->count(),
                'limit' => 300,
                'truncated' => $total > 300,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'case_type' => ['required', Rule::in(['conduct', 'attendance', 'policy', 'grievance', 'other'])],
            'incident_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'summary' => ['required', 'string', 'max:4000'],
        ]);

        $case = DB::transaction(function () use ($data, $user) {
            $employee = Employee::query()
                ->where('company_id', $user->company_id)
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->lockForUpdate()
                ->findOrFail($data['employee_id']);
            abort_unless($employee->status === 'active', 422, 'New cases can only be opened for active employees.');

            $case = DisciplinaryCase::create([
                'company_id' => $user->company_id,
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'created_by' => $user->id,
                'case_type' => $data['case_type'],
                'incident_date' => $data['incident_date'],
                'summary' => trim($data['summary']),
                'status' => 'open',
            ]);
            AuditTrail::record('disciplinary.case_opened', $case, [
                'employee_id' => $employee->id,
                'case_type' => $case->case_type,
            ]);

            return $case;
        });

        return response()->json(['case' => $this->loadCase($case)], 201);
    }

    public function startReview(Request $request, DisciplinaryCase $case): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertCaseScope($user, $case);
        $data = $request->validate([
            'investigator_id' => ['required', 'integer'],
            'investigation_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $case = DB::transaction(function () use ($data, $case, $user) {
            $lockedCase = DisciplinaryCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertCaseScope($user, $lockedCase);
            abort_unless($lockedCase->status === 'open', 409, 'Only open cases can enter review.');
            $investigator = $this->investigatorsFor($user)->findOrFail($data['investigator_id']);
            $lockedCase->update([
                'status' => 'under_review',
                'investigator_id' => $investigator->id,
                'investigation_notes' => trim($data['investigation_notes'] ?? '') ?: null,
            ]);
            AuditTrail::record('disciplinary.review_started', $lockedCase, [
                'investigator_id' => $investigator->id,
            ]);

            return $lockedCase;
        });

        return response()->json(['case' => $this->loadCase($case)]);
    }

    public function recordResponse(Request $request, DisciplinaryCase $case): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertCaseScope($user, $case);
        $data = $request->validate([
            'employee_response' => ['required', 'string', 'max:4000'],
        ]);

        $case = DB::transaction(function () use ($data, $case, $user) {
            $lockedCase = DisciplinaryCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertCaseScope($user, $lockedCase);
            abort_unless($lockedCase->status === 'under_review', 409, 'Employee responses can only be recorded while a case is under review.');
            $lockedCase->update([
                'employee_response' => trim($data['employee_response']),
                'response_recorded_at' => now(),
            ]);
            AuditTrail::record('disciplinary.employee_response_recorded', $lockedCase, [
                'employee_id' => $lockedCase->employee_id,
            ]);

            return $lockedCase;
        });

        return response()->json(['case' => $this->loadCase($case)]);
    }

    public function resolve(Request $request, DisciplinaryCase $case): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertCaseScope($user, $case);
        $data = $request->validate([
            'resolution_summary' => ['required', 'string', 'max:4000'],
        ]);

        $case = DB::transaction(function () use ($data, $case, $user) {
            $lockedCase = DisciplinaryCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->assertCaseScope($user, $lockedCase);
            abort_unless($lockedCase->status === 'under_review', 409, 'Only cases under review can be resolved.');
            $lockedCase->update([
                'status' => 'resolved',
                'resolution_summary' => trim($data['resolution_summary']),
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);
            AuditTrail::record('disciplinary.case_resolved', $lockedCase, [
                'employee_id' => $lockedCase->employee_id,
            ]);

            return $lockedCase;
        });

        return response()->json(['case' => $this->loadCase($case)]);
    }

    private function loadCase(DisciplinaryCase $case): DisciplinaryCase
    {
        return $case->fresh()->load([
            'employee:id,branch_id,employee_code,first_name,last_name,department,status',
            'creator:id,name',
            'investigator:id,name',
            'resolver:id,name',
        ]);
    }

    private function casesFor(User $user): Builder
    {
        return DisciplinaryCase::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function investigatorsFor(User $user): Builder
    {
        return User::query()
            ->where('company_id', $user->company_id)
            ->whereIn('role', ['hr', 'admin'])
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function assertCaseScope(User $user, DisciplinaryCase $case): void
    {
        abort_unless((int) $case->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $case->branch_id !== (int) $user->branch_id, 404);
    }

    private function ensureAccess(User $user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Disciplinary case records are restricted to HR and administrators.');
    }
}
