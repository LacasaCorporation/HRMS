<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\TrainingEnrollment;
use App\Models\TrainingProgram;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GrowthController extends Controller
{
    public function performanceIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);

        $cycles = $this->cyclesFor($user)
            ->with('branch:id,name')
            ->withCount([
                'reviews' => fn (Builder $query) => $query
                    ->when($user->branch_id, fn (Builder $reviews) => $reviews->where('branch_id', $user->branch_id)),
                'reviews as completed_review_count' => fn (Builder $query) => $query
                    ->where('status', 'approved')
                    ->when($user->branch_id, fn (Builder $reviews) => $reviews->where('branch_id', $user->branch_id)),
            ])
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->limit(100)
            ->get();
        $reviews = $this->reviewsFor($user)
            ->with(['cycle:id,title,period_start,period_end,due_date,status', 'branch:id,name'])
            ->orderByRaw("CASE status WHEN 'in_review' THEN 0 WHEN 'changes_requested' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
            ->orderBy('employee_name')
            ->limit(500)
            ->get();

        return response()->json([
            'cycles' => $cycles,
            'reviews' => $reviews,
            'employees' => $this->activeEmployeesFor($user)
                ->with('branch:id,name')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'branches' => $user->company->branches()
                ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'meta' => [
                'cycle_count' => $cycles->count(),
                'open_cycles' => $cycles->where('status', 'open')->count(),
                'reviews_in_progress' => $reviews->whereIn('status', ['draft', 'changes_requested', 'in_review'])->count(),
                'reviews_approved' => $reviews->where('status', 'approved')->count(),
            ],
        ]);
    }

    public function storePerformanceCycle(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:4000'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $user->company_id)],
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        if (($data['due_date'] ?? null) && $data['due_date'] < $data['period_end']) {
            throw ValidationException::withMessages([
                'due_date' => ['The review due date must be on or after the review period end.'],
            ]);
        }
        $branchId = $this->requestedBranch($user, $data['branch_id'] ?? null);
        $employees = $this->activeEmployeesFor($user)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereIn('id', $data['employee_ids'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        if ($employees->count() !== count($data['employee_ids'])) {
            throw ValidationException::withMessages([
                'employee_ids' => ['Select active employees within your company and branch scope.'],
            ]);
        }

        $cycle = DB::transaction(function () use ($data, $user, $branchId, $employees) {
            $cycle = PerformanceCycle::create([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'created_by' => $user->id,
                'title' => $data['title'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => 'open',
            ]);
            foreach ($employees as $employee) {
                $cycle->reviews()->create([
                    'company_id' => $user->company_id,
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                    'created_by' => $user->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => trim($employee->first_name.' '.$employee->last_name),
                    'department' => $employee->department,
                    'status' => 'draft',
                ]);
            }
            AuditTrail::record('performance.cycle_created', $cycle, [
                'review_count' => $employees->count(),
                'branch_id' => $branchId,
            ]);

            return $cycle;
        });

        return response()->json([
            'cycle' => $cycle->load('branch:id,name')->loadCount('reviews'),
        ], 201);
    }

    public function updatePerformanceCycleStatus(Request $request, PerformanceCycle $cycle): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertCycleScope($user, $cycle, true);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);
        if ($data['status'] === 'closed') {
            $hasIncompleteReviews = $cycle->reviews()->where('status', '!=', 'approved')->exists();
            abort_if($hasIncompleteReviews, 409, 'Approve every employee review before closing this cycle.');
        }
        $cycle->update(['status' => $data['status']]);
        AuditTrail::record('performance.cycle_status_updated', $cycle, ['status' => $cycle->status]);

        return response()->json(['cycle' => $cycle->fresh()->load('branch:id,name')->loadCount('reviews')]);
    }

    public function updatePerformanceReview(Request $request, PerformanceReview $review): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertReviewScope($user, $review);
        abort_unless(in_array($review->status, ['draft', 'changes_requested'], true), 409, 'Submitted review inputs are locked.');
        abort_unless($review->cycle?->status === 'open', 409, 'This review cycle is closed.');

        $data = $request->validate([
            'rating' => ['sometimes', 'nullable', 'integer', 'between:1,5'],
            'goals' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'results' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'strengths' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'development_focus' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);
        $review->update($data);
        AuditTrail::record('performance.review_updated', $review, ['cycle_id' => $review->performance_cycle_id]);

        return response()->json(['review' => $review->fresh()->load('cycle:id,title,period_start,period_end,due_date,status')]);
    }

    public function submitPerformanceReview(Request $request, PerformanceReview $review): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertReviewScope($user, $review);
        abort_unless(in_array($review->status, ['draft', 'changes_requested'], true), 409, 'Only draft or returned reviews can be submitted.');
        abort_unless($review->cycle?->status === 'open', 409, 'This review cycle is closed.');
        if ($review->rating === null || !filled($review->goals) || !filled($review->results)) {
            throw ValidationException::withMessages([
                'review' => ['Add a manual rating, goals, and results before submitting this review.'],
            ]);
        }

        $review->update([
            'status' => 'in_review',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'review_note' => null,
        ]);
        AuditTrail::record('performance.review_submitted', $review, ['cycle_id' => $review->performance_cycle_id]);

        return response()->json(['review' => $review->fresh()->load('cycle:id,title,period_start,period_end,due_date,status')]);
    }

    public function reviewPerformanceReview(Request $request, PerformanceReview $review): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertReviewScope($user, $review);
        abort_unless($review->status === 'in_review', 409, 'Only reviews in the queue can be approved or returned.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('decision') === 'return')],
        ]);
        $review->update([
            'status' => $data['decision'] === 'approve' ? 'approved' : 'changes_requested',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);
        AuditTrail::record('performance.review_decided', $review, ['decision' => $data['decision']]);

        return response()->json(['review' => $review->fresh()->load('cycle:id,title,period_start,period_end,due_date,status')]);
    }

    public function trainingIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $programs = $this->programsFor($user)
            ->with('branch:id,name')
            ->withCount([
                'enrollments' => fn (Builder $query) => $query
                    ->when($user->branch_id, fn (Builder $enrollments) => $enrollments->where('branch_id', $user->branch_id)),
                'enrollments as active_enrollment_count' => fn (Builder $query) => $query
                    ->whereIn('status', ['assigned', 'in_progress'])
                    ->when($user->branch_id, fn (Builder $enrollments) => $enrollments->where('branch_id', $user->branch_id)),
                'enrollments as completed_enrollment_count' => fn (Builder $query) => $query
                    ->where('status', 'completed')
                    ->when($user->branch_id, fn (Builder $enrollments) => $enrollments->where('branch_id', $user->branch_id)),
            ])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderBy('start_date')
            ->orderBy('title')
            ->limit(200)
            ->get();
        $enrollments = $this->enrollmentsFor($user)
            ->with(['program:id,title,category,provider,delivery_mode,start_date,due_date,status', 'branch:id,name'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderBy('employee_name')
            ->limit(500)
            ->get();

        return response()->json([
            'programs' => $programs,
            'enrollments' => $enrollments,
            'employees' => $this->activeEmployeesFor($user)
                ->with('branch:id,name')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(),
            'branches' => $user->company->branches()
                ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'meta' => [
                'active_programs' => $programs->where('status', 'active')->count(),
                'active_enrollments' => $enrollments->whereIn('status', ['assigned', 'in_progress'])->count(),
                'completed_enrollments' => $enrollments->where('status', 'completed')->count(),
                'overdue_enrollments' => $enrollments->filter(fn (TrainingEnrollment $enrollment) =>
                    in_array($enrollment->status, ['assigned', 'in_progress'], true)
                    && $enrollment->program?->due_date?->lt(today()))->count(),
            ],
        ]);
    }

    public function storeTrainingProgram(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(['leadership', 'technical', 'compliance', 'safety', 'onboarding', 'professional', 'other'])],
            'provider' => ['nullable', 'string', 'max:180'],
            'delivery_mode' => ['required', Rule::in(['classroom', 'virtual', 'self_paced', 'blended'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'capacity' => ['nullable', 'integer', 'between:1,100000'],
            'description' => ['nullable', 'string', 'max:4000'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $user->company_id)],
        ]);
        if (($data['start_date'] ?? null) && ($data['due_date'] ?? null) && $data['due_date'] < $data['start_date']) {
            throw ValidationException::withMessages([
                'due_date' => ['The due date must be on or after the program start date.'],
            ]);
        }
        $branchId = $this->requestedBranch($user, $data['branch_id'] ?? null);
        $program = TrainingProgram::create([
            ...$data,
            'company_id' => $user->company_id,
            'branch_id' => $branchId,
            'created_by' => $user->id,
            'status' => 'active',
        ]);
        AuditTrail::record('training.program_created', $program, ['branch_id' => $branchId]);

        return response()->json(['program' => $program->load('branch:id,name')], 201);
    }

    public function updateTrainingProgram(Request $request, TrainingProgram $program): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertProgramScope($user, $program, true);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'category' => ['sometimes', 'required', Rule::in(['leadership', 'technical', 'compliance', 'safety', 'onboarding', 'professional', 'other'])],
            'provider' => ['sometimes', 'nullable', 'string', 'max:180'],
            'delivery_mode' => ['sometimes', 'required', Rule::in(['classroom', 'virtual', 'self_paced', 'blended'])],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'between:1,100000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'archived'])],
        ]);
        $start = $data['start_date'] ?? $program->start_date?->toDateString();
        $due = array_key_exists('due_date', $data) ? $data['due_date'] : $program->due_date?->toDateString();
        if ($start && $due && $due < $start) {
            throw ValidationException::withMessages([
                'due_date' => ['The due date must be on or after the program start date.'],
            ]);
        }
        if (array_key_exists('capacity', $data) && $data['capacity'] !== null) {
            $activeCount = $program->enrollments()->whereIn('status', ['assigned', 'in_progress'])->count();
            if ($data['capacity'] < $activeCount) {
                throw ValidationException::withMessages([
                    'capacity' => ['Capacity cannot be lower than the number of active enrollments.'],
                ]);
            }
        }
        $program->update($data);
        AuditTrail::record('training.program_updated', $program, ['status' => $program->status]);

        return response()->json(['program' => $program->fresh()->load('branch:id,name')]);
    }

    public function storeTrainingEnrollment(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'training_program_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $program = $this->programsFor($user)->whereKey($data['training_program_id'])->firstOrFail();
        abort_unless($program->status === 'active', 409, 'Choose an active training program.');
        $employee = $this->activeEmployeesFor($user)->whereKey($data['employee_id'])->firstOrFail();
        if ($program->branch_id && (int) $program->branch_id !== (int) $employee->branch_id) {
            throw ValidationException::withMessages([
                'employee_id' => ['Choose an employee from the program branch.'],
            ]);
        }

        try {
            $enrollment = DB::transaction(function () use ($program, $employee, $data, $user) {
                $lockedProgram = TrainingProgram::query()->lockForUpdate()->findOrFail($program->id);
                $exists = TrainingEnrollment::query()
                    ->where('training_program_id', $program->id)
                    ->where('employee_code', $employee->employee_code)
                    ->exists();
                abort_if($exists, 409, 'This employee already has a record for this program.');
                $activeCount = $lockedProgram->enrollments()->whereIn('status', ['assigned', 'in_progress'])->count();
                abort_if($lockedProgram->capacity && $activeCount >= $lockedProgram->capacity, 409, 'This program has reached its enrollment capacity.');

                $enrollment = TrainingEnrollment::create([
                    'company_id' => $user->company_id,
                    'branch_id' => $employee->branch_id,
                    'training_program_id' => $lockedProgram->id,
                    'employee_id' => $employee->id,
                    'assigned_by' => $user->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => trim($employee->first_name.' '.$employee->last_name),
                    'department' => $employee->department,
                    'status' => 'assigned',
                    'notes' => $data['notes'] ?? null,
                ]);
                AuditTrail::record('training.enrollment_created', $enrollment, ['program_id' => $lockedProgram->id]);

                return $enrollment;
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This employee already has a record for this program.');
        }

        return response()->json([
            'enrollment' => $enrollment->load(['program:id,title,category,provider,delivery_mode,start_date,due_date,status', 'branch:id,name']),
        ], 201);
    }

    public function updateTrainingEnrollment(Request $request, TrainingEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertEnrollmentScope($user, $enrollment);
        $data = $request->validate([
            'status' => ['required', Rule::in(['assigned', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $allowedTransitions = [
            'assigned' => ['assigned', 'in_progress', 'completed', 'cancelled'],
            'in_progress' => ['in_progress', 'completed', 'cancelled'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        ];
        abort_unless(in_array($data['status'], $allowedTransitions[$enrollment->status], true), 409, 'This training enrollment cannot move back to an earlier status.');
        $enrollment->update([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'completed_at' => $data['status'] === 'completed' ? ($enrollment->completed_at ?? now()) : null,
        ]);
        AuditTrail::record('training.enrollment_updated', $enrollment, ['status' => $enrollment->status]);

        return response()->json([
            'enrollment' => $enrollment->fresh()->load(['program:id,title,category,provider,delivery_mode,start_date,due_date,status', 'branch:id,name']),
        ]);
    }

    private function cyclesFor($user): Builder
    {
        return PerformanceCycle::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->where('branch_id', $user->branch_id)
                ->orWhereHas('reviews', fn (Builder $reviews) => $reviews->where('branch_id', $user->branch_id))));
    }

    private function reviewsFor($user): Builder
    {
        return PerformanceReview::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function programsFor($user): Builder
    {
        return TrainingProgram::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->whereNull('branch_id')
                ->orWhere('branch_id', $user->branch_id)));
    }

    private function enrollmentsFor($user): Builder
    {
        return TrainingEnrollment::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function activeEmployeesFor($user): Builder
    {
        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function requestedBranch($user, ?int $branchId): ?int
    {
        if ($user->branch_id && $branchId !== null && (int) $user->branch_id !== $branchId) {
            abort(403, 'This branch is outside your access scope.');
        }

        return $user->branch_id ?: $branchId;
    }

    private function assertReviewScope($user, PerformanceReview $review): void
    {
        abort_unless((int) $review->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $review->branch_id !== (int) $user->branch_id, 404);
    }

    private function assertCycleScope($user, PerformanceCycle $cycle, bool $forWrite = false): void
    {
        abort_unless((int) $cycle->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && $cycle->branch_id && (int) $cycle->branch_id !== (int) $user->branch_id, 404);
        abort_if($forWrite && $user->branch_id && !$cycle->branch_id, 403, 'Company-wide review cycles can only be changed by company HR or administrators.');
        if ($user->branch_id && !$cycle->reviews()->where('branch_id', $user->branch_id)->exists()) {
            abort(404);
        }
    }

    private function assertProgramScope($user, TrainingProgram $program, bool $forWrite = false): void
    {
        abort_unless((int) $program->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && $program->branch_id && (int) $program->branch_id !== (int) $user->branch_id, 404);
        abort_if($forWrite && $user->branch_id && !$program->branch_id, 403, 'Company-wide programs can only be changed by company HR or administrators.');
    }

    private function assertEnrollmentScope($user, TrainingEnrollment $enrollment): void
    {
        abort_unless((int) $enrollment->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $enrollment->branch_id !== (int) $user->branch_id, 404);
    }

    private function ensureAccess($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Growth and training records are restricted to HR and administrators.');
    }
}
