<?php

namespace App\Http\Controllers;

use App\Models\BenefitEnrollment;
use App\Models\BenefitPlan;
use App\Models\Employee;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BenefitsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $plans = $this->plansFor($user)
            ->with(['branch:id,name'])
            ->withCount(['enrollments as active_enrollment_count' => fn (Builder $query) => $query
                ->whereIn('status', ['approved', 'in_review', 'draft', 'changes_requested'])
                ->where(fn (Builder $effective) => $effective
                    ->whereNull('effective_end')
                    ->orWhereDate('effective_end', '>=', today()))])
            ->orderBy('name')
            ->get();
        $enrollments = $this->enrollmentsFor($user)
            ->with([
                'plan:id,name,category,currency,provider',
                'branch:id,name',
                'creator:id,name',
                'submittedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->orderByDesc('effective_start')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return response()->json([
            'plans' => $plans,
            'enrollments' => $enrollments,
            'employees' => Employee::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'department', 'currency']),
            'branches' => $user->company->branches()
                ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'meta' => [
                'active_plans' => $plans->where('status', 'active')->count(),
                'active_enrollments' => $enrollments->whereIn('status', ['approved', 'in_review', 'draft', 'changes_requested'])
                    ->where(fn ($enrollment) => !$enrollment->effective_end || $enrollment->effective_end->gte(today()))->count(),
                'pending_review' => $enrollments->where('status', 'in_review')->count(),
                'needs_attention' => $enrollments->whereIn('status', ['draft', 'changes_requested'])->count(),
            ],
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'category' => ['required', Rule::in(['allowance', 'insurance', 'retirement', 'wellbeing', 'other'])],
            'currency' => ['required', Rule::in(['AED', 'INR', 'SAR'])],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $user->company_id)],
            'provider' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'eligibility_notes' => ['nullable', 'string', 'max:4000'],
        ]);
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($user->branch_id && $branchId !== null && (int) $user->branch_id !== $branchId) {
            abort(403, 'This branch is outside your access scope.');
        }
        $this->assertUniquePlanName($user->company_id, $branchId, $data['name']);

        $plan = BenefitPlan::create([
            ...$data,
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id ?: $branchId,
            'created_by' => $user->id,
            'status' => 'active',
        ]);
        AuditTrail::record('benefits.plan_created', $plan, ['category' => $plan->category, 'currency' => $plan->currency]);

        return response()->json(['plan' => $plan->load('branch:id,name')], 201);
    }

    public function updatePlan(Request $request, BenefitPlan $plan): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertPlanScope($user, $plan, true);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:140'],
            'category' => ['sometimes', 'required', Rule::in(['allowance', 'insurance', 'retirement', 'wellbeing', 'other'])],
            'currency' => ['sometimes', 'required', Rule::in(['AED', 'INR', 'SAR'])],
            'provider' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'eligibility_notes' => ['nullable', 'string', 'max:4000'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'archived'])],
        ]);
        if (isset($data['currency']) && $data['currency'] !== $plan->currency && $plan->enrollments()->exists()) {
            throw ValidationException::withMessages([
                'currency' => ['A plan currency cannot change after enrollments have been created.'],
            ]);
        }
        if (isset($data['name']) && $data['name'] !== $plan->name) {
            $this->assertUniquePlanName($plan->company_id, $plan->branch_id, $data['name'], $plan->id);
        }

        $plan->update($data);
        AuditTrail::record('benefits.plan_updated', $plan, ['status' => $plan->status]);

        return response()->json(['plan' => $plan->fresh()->load('branch:id,name')]);
    }

    public function storeEnrollment(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'benefit_plan_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'effective_start' => ['required', 'date_format:Y-m-d'],
            'effective_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $plan = $this->plansFor($user)->whereKey($data['benefit_plan_id'])->firstOrFail();
        abort_unless($plan->status === 'active', 409, 'Choose an active benefit plan.');
        $employee = Employee::query()
            ->where('company_id', $user->company_id)
            ->where('status', 'active')
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->whereKey($data['employee_id'])
            ->firstOrFail();
        if ($employee->currency !== $plan->currency) {
            throw ValidationException::withMessages([
                'employee_id' => ['The employee currency must match the benefit plan currency.'],
            ]);
        }

        $this->assertNoOverlappingEnrollment($plan, $employee, $data['effective_start'], $data['effective_end'] ?? null);
        try {
            $enrollment = BenefitEnrollment::create([
                'company_id' => $user->company_id,
                'branch_id' => $employee->branch_id,
                'benefit_plan_id' => $plan->id,
                'employee_id' => $employee->id,
                'created_by' => $user->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => trim($employee->first_name.' '.$employee->last_name),
                'department' => $employee->department,
                'effective_start' => $data['effective_start'],
                'effective_end' => $data['effective_end'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
            ]);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'An enrollment already exists for this plan, employee, and start date.');
        }
        AuditTrail::record('benefits.enrollment_created', $enrollment, ['benefit_plan_id' => $plan->id]);

        return response()->json(['enrollment' => $enrollment->load(['plan:id,name,category,currency,provider', 'branch:id,name'])], 201);
    }

    public function updateEnrollment(Request $request, BenefitEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertEnrollmentScope($user, $enrollment);
        abort_unless(in_array($enrollment->status, ['draft', 'changes_requested'], true), 409, 'Submitted or approved enrollment inputs are locked.');

        $data = $request->validate([
            'effective_start' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'effective_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_start'],
            'employee_contribution' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'employer_contribution' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $start = $data['effective_start'] ?? $enrollment->effective_start->toDateString();
        $end = $data['effective_end'] ?? $enrollment->effective_end?->toDateString();
        $this->assertNoOverlappingEnrollment($enrollment->plan, $enrollment->employee, $start, $end, $enrollment->id);
        $enrollment->update($data);
        AuditTrail::record('benefits.enrollment_updated', $enrollment, ['benefit_plan_id' => $enrollment->benefit_plan_id]);

        return response()->json(['enrollment' => $enrollment->fresh()->load(['plan:id,name,category,currency,provider'])]);
    }

    public function submitEnrollment(Request $request, BenefitEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertEnrollmentScope($user, $enrollment);
        abort_unless(in_array($enrollment->status, ['draft', 'changes_requested'], true), 409, 'Only draft or returned enrollments can be submitted.');
        abort_if($enrollment->employee_contribution === null || $enrollment->employer_contribution === null, 422, 'Save both manual contribution inputs before submitting.');

        $enrollment->update([
            'status' => 'in_review',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'review_note' => null,
        ]);
        AuditTrail::record('benefits.enrollment_submitted', $enrollment, ['benefit_plan_id' => $enrollment->benefit_plan_id]);

        return response()->json(['enrollment' => $enrollment->fresh()->load(['plan:id,name,category,currency,provider', 'submittedBy:id,name'])]);
    }

    public function reviewEnrollment(Request $request, BenefitEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertEnrollmentScope($user, $enrollment);
        abort_unless($enrollment->status === 'in_review', 409, 'Only enrollments in review can be approved or returned.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return', 'decline'])],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => in_array($request->input('decision'), ['return', 'decline'], true))],
        ]);
        $enrollment->update([
            'status' => match ($data['decision']) {
                'approve' => 'approved',
                'return' => 'changes_requested',
                default => 'declined',
            },
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);
        AuditTrail::record('benefits.enrollment_reviewed', $enrollment, ['decision' => $data['decision']]);

        return response()->json(['enrollment' => $enrollment->fresh()->load(['plan:id,name,category,currency,provider', 'reviewedBy:id,name'])]);
    }

    public function endEnrollment(Request $request, BenefitEnrollment $enrollment): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertEnrollmentScope($user, $enrollment);
        abort_unless($enrollment->status === 'approved', 409, 'Only approved enrollments can be ended.');
        $data = $request->validate([
            'effective_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:effective_start'],
        ]);
        $this->assertNoOverlappingEnrollment($enrollment->plan, $enrollment->employee, $enrollment->effective_start->toDateString(), $data['effective_end'], $enrollment->id);
        $enrollment->update(['status' => 'ended', 'effective_end' => $data['effective_end']]);
        AuditTrail::record('benefits.enrollment_ended', $enrollment, ['benefit_plan_id' => $enrollment->benefit_plan_id]);

        return response()->json(['enrollment' => $enrollment->fresh()->load('plan:id,name,category,currency')]);
    }

    private function plansFor($user): Builder
    {
        return BenefitPlan::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->whereNull('branch_id')
                ->orWhere('branch_id', $user->branch_id)));
    }

    private function enrollmentsFor($user): Builder
    {
        return BenefitEnrollment::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function assertPlanScope($user, BenefitPlan $plan, bool $forWrite = false): void
    {
        abort_unless((int) $plan->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && $plan->branch_id && (int) $plan->branch_id !== (int) $user->branch_id, 404);
        abort_if($forWrite && $user->branch_id && !$plan->branch_id, 403, 'Company-wide benefit plans can only be changed by company HR or administrators.');
    }

    private function assertEnrollmentScope($user, BenefitEnrollment $enrollment): void
    {
        abort_unless((int) $enrollment->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $enrollment->branch_id !== (int) $user->branch_id, 404);
    }

    private function assertUniquePlanName(int $companyId, ?int $branchId, string $name, ?int $exceptId = null): void
    {
        $exists = BenefitPlan::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId), fn (Builder $query) => $query->whereNull('branch_id'))
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
        abort_if($exists, 409, 'A benefit plan with this name already exists in the selected scope.');
    }

    private function assertNoOverlappingEnrollment(BenefitPlan $plan, ?Employee $employee, string $start, ?string $end, ?int $exceptId = null): void
    {
        if (!$employee) {
            abort(409, 'The employee record for this enrollment is no longer available.');
        }
        $overlap = BenefitEnrollment::query()
            ->where('benefit_plan_id', $plan->id)
            ->where('employee_id', $employee->id)
            ->whereNotIn('status', ['declined', 'ended'])
            ->whereDate('effective_start', '<=', $end ?? '9999-12-31')
            ->where(fn (Builder $query) => $query->whereNull('effective_end')->orWhereDate('effective_end', '>=', $start))
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages([
                'effective_start' => ['This employee already has an overlapping enrollment in the selected plan.'],
            ]);
        }
    }

    private function ensureAccess($user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Benefit records are restricted to HR and administrators.');
    }
}
