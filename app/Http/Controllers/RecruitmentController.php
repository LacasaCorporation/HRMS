<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Company;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequisition;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecruitmentController extends Controller
{
    private const PIPELINE_STAGES = ['new', 'screening', 'interview', 'offer'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);

        $requisitions = $this->requisitionsFor($user)
            ->with(['branch:id,name', 'department:id,name', 'position:id,title,code'])
            ->withCount('candidates')
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'on_hold' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->get();

        $candidateFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', Rule::in(['new', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'])],
            'requisition_id' => ['nullable', 'integer'],
        ]);
        $candidateQuery = RecruitmentCandidate::query()
            ->where('company_id', $user->company_id)
            ->whereIn('requisition_id', $requisitions->modelKeys())
            ->when($candidateFilters['stage'] ?? null, fn (Builder $query, string $stage) => $query->where('stage', $stage))
            ->when($candidateFilters['requisition_id'] ?? null, fn (Builder $query, int $requisitionId) => $query->where('requisition_id', $requisitionId))
            ->when($candidateFilters['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.mb_strtolower(trim($search)).'%';
                $query->where(fn (Builder $scope) => $scope
                    ->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereHas('requisition', fn (Builder $requisition) => $requisition
                        ->whereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$term])));
            });
        $candidates = (clone $candidateQuery)
            ->with([
                'requisition:id,code,title,department_id,branch_id,status',
                'requisition.department:id,name',
                'requisition.branch:id,name',
                'hiredEmployee:id,employee_code',
            ])
            ->orderByDesc('applied_at')
            ->paginate(100);

        $allStageCounts = RecruitmentCandidate::query()
            ->where('company_id', $user->company_id)
            ->whereIn('requisition_id', $requisitions->modelKeys())
            ->select('stage', DB::raw('COUNT(*) as total'))
            ->groupBy('stage')
            ->pluck('total', 'stage');
        $activeCandidateCount = $allStageCounts->except(['hired', 'rejected', 'withdrawn'])->sum();
        $hiresThisMonth = RecruitmentCandidate::query()
            ->where('company_id', $user->company_id)
            ->whereIn('requisition_id', $requisitions->modelKeys())
            ->where('stage', 'hired')
            ->whereBetween('hired_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return response()->json([
            'requisitions' => $requisitions,
            'candidates' => $candidates->items(),
            'candidate_pagination' => [
                'current_page' => $candidates->currentPage(),
                'last_page' => $candidates->lastPage(),
                'per_page' => $candidates->perPage(),
                'total' => $candidates->total(),
            ],
            'departments' => Department::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $user->branch_id)))
                ->orderBy('name')
                ->get(['id', 'name', 'branch_id']),
            'positions' => Position::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'open')
                ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $user->branch_id)))
                ->with('department:id,name')
                ->orderBy('title')
                ->get(['id', 'title', 'code', 'department_id', 'branch_id', 'headcount']),
            'pipeline' => collect(['new', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'])
                ->mapWithKeys(fn (string $stage) => [$stage => $allStageCounts->get($stage, 0)]),
            'metrics' => [
                'open_requisitions' => $requisitions->where('status', 'open')->count(),
                'active_candidates' => $activeCandidateCount,
                'interviews' => $allStageCounts->get('interview', 0),
                'hires_this_month' => $hiresThisMonth,
                'openings' => $requisitions->where('status', 'open')->sum('openings'),
            ],
        ]);
    }

    public function storeRequisition(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40',
                Rule::unique('recruitment_requisitions', 'code')->where('company_id', $user->company_id),
            ],
            'title' => ['required', 'string', 'max:150'],
            'branch_id' => [
                'nullable', 'integer',
                Rule::exists('branches', 'id')->where('company_id', $user->company_id),
            ],
            'department_id' => [
                'nullable', 'integer',
                Rule::exists('departments', 'id')->where('company_id', $user->company_id),
            ],
            'position_id' => [
                'nullable', 'integer',
                Rule::exists('positions', 'id')->where('company_id', $user->company_id),
            ],
            'employment_type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Temporary'])],
            'work_mode' => ['required', Rule::in(['On-site', 'Hybrid', 'Remote'])],
            'location' => ['nullable', 'string', 'max:120'],
            'openings' => ['required', 'integer', 'min:1', 'max:100'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'target_date' => ['nullable', 'date', 'after_or_equal:today'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['draft', 'open'])],
        ]);

        $data['branch_id'] = $data['branch_id'] ?? $user->branch_id;
        $this->assertBranchScope($user, $data['branch_id']);
        $this->assertDepartmentScope($user, $data['department_id'] ?? null, $data['branch_id']);
        $this->assertPositionScope(
            $user,
            $data['position_id'] ?? null,
            $data['department_id'] ?? null,
            $data['branch_id'],
            $data['openings'],
        );

        $requisition = RecruitmentRequisition::create($data + [
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'status' => 'open',
        ]);
        AuditTrail::record('recruitment.requisition_created', $requisition, [
            'code' => $requisition->code,
            'status' => $requisition->status,
        ]);

        return response()->json([
            'requisition' => $requisition->load(['branch:id,name', 'department:id,name', 'position:id,title,code']),
        ], 201);
    }

    public function updateRequisitionStatus(Request $request, RecruitmentRequisition $requisition): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        $this->assertRequisitionScope($user, $requisition);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'on_hold', 'closed'])],
        ]);
        abort_if($requisition->status === 'filled', 409, 'A filled requisition cannot be reopened or changed.');

        $requisition->update($data);
        AuditTrail::record('recruitment.requisition_status_updated', $requisition, ['status' => $data['status']]);

        return response()->json(['requisition' => $requisition->fresh()]);
    }

    public function storeCandidate(Request $request, RecruitmentRequisition $requisition): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        $this->assertRequisitionScope($user, $requisition);
        abort_unless($requisition->status === 'open', 409, 'Candidates can only be added to open requisitions.');

        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('recruitment_candidates', 'email')
                    ->where('company_id', $user->company_id)
                    ->where('requisition_id', $requisition->id),
            ],
            'phone' => ['nullable', 'string', 'max:40'],
            'source' => ['required', Rule::in(['Direct', 'Referral', 'LinkedIn', 'Job board', 'Agency', 'Other'])],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $candidate = RecruitmentCandidate::create($data + [
            'company_id' => $user->company_id,
            'requisition_id' => $requisition->id,
            'created_by' => $user->id,
            'stage' => 'new',
            'applied_at' => now(),
        ]);
        AuditTrail::record('recruitment.candidate_added', $candidate, [
            'requisition_id' => $requisition->id,
            'source' => $candidate->source,
        ]);

        return response()->json([
            'candidate' => $candidate->load('requisition:id,code,title'),
        ], 201);
    }

    public function updateCandidate(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($candidate->company_id === $user->company_id, 404);
        $requisition = $candidate->requisition;
        $this->assertRequisitionScope($user, $requisition);
        abort_if($candidate->hired_employee_id !== null || in_array($candidate->stage, ['hired', 'rejected', 'withdrawn'], true), 409, 'This candidate is no longer in an active pipeline.');

        $data = $request->validate([
            'stage' => ['required', Rule::in(['screening', 'interview', 'offer', 'rejected', 'withdrawn'])],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        return DB::transaction(function () use ($candidate, $data, $user) {
            $lockedCandidate = RecruitmentCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            abort_if(
                $lockedCandidate->company_id !== $user->company_id
                    || $lockedCandidate->hired_employee_id !== null
                    || in_array($lockedCandidate->stage, ['hired', 'rejected', 'withdrawn'], true),
                409,
                'This candidate is no longer in an active pipeline.',
            );
            if (in_array($data['stage'], self::PIPELINE_STAGES, true)) {
                $currentIndex = array_search($lockedCandidate->stage, self::PIPELINE_STAGES, true);
                $nextIndex = array_search($data['stage'], self::PIPELINE_STAGES, true);
                if ($nextIndex < $currentIndex) {
                    throw ValidationException::withMessages([
                        'stage' => ['Move candidates forward through the pipeline. Terminal stages cannot be changed.'],
                    ]);
                }
            }

            $lockedCandidate->update($data);
            AuditTrail::record('recruitment.candidate_stage_updated', $lockedCandidate, [
                'stage' => $lockedCandidate->stage,
                'rating' => $lockedCandidate->rating,
            ]);

            return response()->json([
                'candidate' => $lockedCandidate->fresh()->load('requisition:id,code,title'),
            ]);
        });
    }

    public function hireCandidate(Request $request, RecruitmentCandidate $candidate): JsonResponse
    {
        $user = $request->user();
        $this->ensurePeopleManager($user);
        abort_unless($candidate->company_id === $user->company_id, 404);
        $this->assertRequisitionScope($user, $candidate->requisition);
        abort_unless($candidate->stage === 'offer', 409, 'Move the candidate to the offer stage before hiring.');

        $data = $request->validate([
            'employee_code' => [
                'required', 'string', 'max:32',
                Rule::unique('employees', 'employee_code')->where('company_id', $user->company_id),
            ],
            'join_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        return DB::transaction(function () use ($candidate, $data, $user) {
            Company::query()->lockForUpdate()->findOrFail($user->company_id);
            abort_if(
                Employee::query()
                    ->where('company_id', $user->company_id)
                    ->where('employee_code', $data['employee_code'])
                    ->exists(),
                409,
                'That employee code has already been used.',
            );
            $requisition = RecruitmentRequisition::query()->lockForUpdate()->findOrFail($candidate->requisition_id);
            $lockedCandidate = RecruitmentCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            abort_if(
                $lockedCandidate->hired_employee_id !== null || in_array($lockedCandidate->stage, ['hired', 'rejected', 'withdrawn'], true),
                409,
                'This candidate is no longer available to hire.',
            );
            abort_unless($lockedCandidate->stage === 'offer', 409, 'Move the candidate to the offer stage before hiring.');
            abort_if($requisition->status === 'filled' || $requisition->candidates()->where('stage', 'hired')->count() >= $requisition->openings, 409, 'All openings for this requisition have been filled.');

            $department = $requisition->department;
            $position = null;
            if ($requisition->position_id) {
                $position = Position::query()->lockForUpdate()->findOrFail($requisition->position_id);
                abort_unless($position->status === 'open', 409, 'The linked workforce position is not open.');
                abort_if(
                    Employee::query()->where('position_id', $position->id)->count() >= $position->headcount,
                    409,
                    'The linked workforce position has reached its planned headcount.',
                );
            }

            $employee = Employee::create([
                'company_id' => $user->company_id,
                'branch_id' => $requisition->branch_id,
                'department_id' => $department?->id,
                'position_id' => $position?->id,
                'employee_code' => $data['employee_code'],
                'first_name' => $lockedCandidate->first_name,
                'last_name' => $lockedCandidate->last_name,
                'work_email' => $lockedCandidate->email,
                'phone' => $lockedCandidate->phone,
                'job_title' => $requisition->title,
                'department' => $department?->name,
                'employment_type' => $requisition->employment_type,
                'status' => 'active',
                'join_date' => $data['join_date'],
            ]);

            $lockedCandidate->update([
                'stage' => 'hired',
                'hired_employee_id' => $employee->id,
                'hired_at' => now(),
            ]);

            if ($requisition->candidates()->where('stage', 'hired')->count() >= $requisition->openings) {
                $requisition->update(['status' => 'filled']);
            }
            if ($position && Employee::query()->where('position_id', $position->id)->count() >= $position->headcount) {
                $position->update(['status' => 'filled']);
            }

            AuditTrail::record('recruitment.candidate_hired', $lockedCandidate, [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
            ]);

            return response()->json([
                'candidate' => $lockedCandidate->fresh()->load('requisition:id,code,title'),
                'employee' => $employee,
            ]);
        });
    }

    private function requisitionsFor($user): Builder
    {
        return RecruitmentRequisition::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->whereNull('branch_id')
                ->orWhere('branch_id', $user->branch_id)));
    }

    private function ensurePeopleManager($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Recruitment records are restricted to HR and administrators.');
    }

    private function assertRequisitionScope($user, RecruitmentRequisition $requisition): void
    {
        abort_unless($requisition->company_id === $user->company_id, 404);
        if ($user->branch_id && $requisition->branch_id && $requisition->branch_id !== $user->branch_id) {
            abort(404);
        }
    }

    private function assertBranchScope($user, ?int $branchId): void
    {
        if ($user->branch_id && $user->branch_id !== $branchId) {
            abort(403, 'This branch is outside your organization scope.');
        }
    }

    private function assertDepartmentScope($user, ?int $departmentId, ?int $branchId): void
    {
        if ($departmentId === null) {
            return;
        }

        $department = Department::query()
            ->where('company_id', $user->company_id)
            ->whereKey($departmentId)
            ->firstOrFail();
        abort_unless($department->status === 'active', 422, 'Choose an active department.');
        abort_if($department->branch_id && $department->branch_id !== $branchId, 422, 'The department belongs to a different branch.');
    }

    private function assertPositionScope(
        $user,
        ?int $positionId,
        ?int $departmentId,
        ?int $branchId,
        int $openings,
    ): void {
        if ($positionId === null) {
            return;
        }

        $position = Position::query()
            ->where('company_id', $user->company_id)
            ->whereKey($positionId)
            ->firstOrFail();
        abort_unless($position->status === 'open', 422, 'Choose an open workforce position.');
        abort_if($departmentId && $position->department_id !== $departmentId, 422, 'The selected position belongs to a different department.');
        abort_if($position->branch_id && $position->branch_id !== $branchId, 422, 'The selected position belongs to a different branch.');
        abort_if(
            Employee::query()->where('position_id', $position->id)->count() + $openings > $position->headcount,
            422,
            'The requisition openings exceed the remaining planned headcount.',
        );
    }
}
