<?php

namespace App\Http\Controllers;

use App\Models\TravelRequest;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TravelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $scope = $this->travelFor($user);
        $trips = (clone $scope)
            ->with(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'reconciler:id,name'])
            ->orderByDesc('departure_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $trips->each(function (TravelRequest $trip) use ($user) {
            $canReview = $trip->status === 'pending'
                && (int) $trip->submitted_by !== (int) $user->id
                && PeopleScope::canReview($user, $trip->employee);
            $trip->setAttribute('can_review', $canReview);
            $trip->setAttribute('can_submit_report', $trip->status === 'approved'
                && (int) $trip->submitted_by === (int) $user->id
                && $trip->return_date->isBefore(today()));
            $trip->setAttribute('can_reconcile', $user->canManagePeople()
                && $trip->status === 'report_submitted'
                && (int) $trip->submitted_by !== (int) $user->id
                && PeopleScope::canReview($user, $trip->employee));
        });
        $total = (clone $scope)->count();

        return response()->json([
            'trips' => $trips,
            'meta' => [
                'total' => $total,
                'pending' => (clone $scope)->where('status', 'pending')->count(),
                'approved' => (clone $scope)->where('status', 'approved')->count(),
                'report_submitted' => (clone $scope)->where('status', 'report_submitted')->count(),
                'reconciled' => (clone $scope)->where('status', 'reconciled')->count(),
                'limit' => 300,
                'truncated' => $total > 300,
                'can_request' => (bool) PeopleScope::employees($user)->where('user_id', $user->id)->where('status', 'active')->exists(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $employee = PeopleScope::employees($user)->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $data = $request->validate([
            'origin' => ['required', 'string', 'max:140'],
            'destination' => ['required', 'string', 'max:140'],
            'purpose' => ['required', 'string', 'max:2000'],
            'departure_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:departure_date'],
            'estimated_budget' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'itinerary_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $trip = TravelRequest::create([
            'company_id' => $employee->company_id,
            'branch_id' => $employee->branch_id,
            'employee_id' => $employee->id,
            'submitted_by' => $user->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => trim($employee->first_name.' '.$employee->last_name),
            'department' => $employee->department,
            'origin' => trim($data['origin']),
            'destination' => trim($data['destination']),
            'purpose' => trim($data['purpose']),
            'departure_date' => $data['departure_date'],
            'return_date' => $data['return_date'],
            'currency' => $employee->currency ?: $user->company?->currency ?: 'USD',
            'estimated_budget' => $data['estimated_budget'],
            'itinerary_notes' => trim($data['itinerary_notes'] ?? '') ?: null,
            'status' => 'pending',
        ]);
        AuditTrail::record('travel.request_submitted', $trip, [
            'employee_id' => $employee->id,
            'estimated_budget' => $trip->estimated_budget,
            'currency' => $trip->currency,
        ]);

        return response()->json(['trip' => $this->decorateTrip($trip->load(['employee:id,company_id,manager_id,user_id']), $user)], 201);
    }

    public function review(Request $request, TravelRequest $trip): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertTripScope($user, $trip);
        abort_unless(PeopleScope::canReview($user, $trip->employee), 403, 'You may only review travel requests from your direct reports or authorized people scope.');
        abort_if((int) $trip->submitted_by === (int) $user->id, 403, 'You cannot review your own travel request.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'approved_budget' => ['required_if:decision,approve', 'nullable', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'review_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('decision') === 'reject')],
        ]);
        if ($data['decision'] === 'reject' && ! trim($data['review_note'] ?? '')) {
            throw ValidationException::withMessages([
                'review_note' => ['Add a reason so the traveler understands why the request was rejected.'],
            ]);
        }
        if ($data['decision'] === 'approve' && $this->toCents($data['approved_budget']) > $this->toCents($trip->estimated_budget)) {
            throw ValidationException::withMessages([
                'approved_budget' => ['The approved budget cannot exceed the requested estimate.'],
            ]);
        }

        $trip = DB::transaction(function () use ($data, $trip, $user) {
            $lockedTrip = TravelRequest::query()->with('employee')->lockForUpdate()->findOrFail($trip->id);
            $this->assertTripScope($user, $lockedTrip);
            abort_unless(PeopleScope::canReview($user, $lockedTrip->employee), 403);
            abort_if((int) $lockedTrip->submitted_by === (int) $user->id, 403, 'You cannot review your own travel request.');
            abort_unless($lockedTrip->status === 'pending', 409, 'This travel request has already been reviewed.');
            if ($data['decision'] === 'approve') {
                abort_if($this->toCents($data['approved_budget']) > $this->toCents($lockedTrip->estimated_budget), 422, 'The approved budget cannot exceed the requested estimate.');
            }
            $lockedTrip->update([
                'status' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'approved_budget' => $data['decision'] === 'approve' ? $data['approved_budget'] : null,
                'review_note' => trim($data['review_note'] ?? '') ?: null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
            AuditTrail::record('travel.request_'.$lockedTrip->status, $lockedTrip, [
                'employee_id' => $lockedTrip->employee_id,
                'approved_budget' => $lockedTrip->approved_budget,
                'review_note' => $lockedTrip->review_note,
            ]);

            return $lockedTrip;
        });

        return response()->json(['trip' => $this->decorateTrip(
            $trip->fresh()->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'reconciler:id,name']),
            $user,
        )]);
    }

    public function submitReport(Request $request, TravelRequest $trip): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertTripScope($user, $trip);
        abort_unless((int) $trip->submitted_by === (int) $user->id, 403, 'Only the traveler can submit the post-trip report.');
        abort_unless($trip->status === 'approved', 409, 'Only approved trips can be reported.');
        abort_unless($trip->return_date->isBefore(today()), 409, 'Submit the post-trip report after the scheduled return date.');

        $data = $request->validate([
            'actual_total' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'post_trip_note' => ['required', 'string', 'max:4000'],
        ]);

        $trip = DB::transaction(function () use ($data, $trip, $user) {
            $lockedTrip = TravelRequest::query()->with('employee')->lockForUpdate()->findOrFail($trip->id);
            $this->assertTripScope($user, $lockedTrip);
            abort_unless((int) $lockedTrip->submitted_by === (int) $user->id, 403, 'Only the traveler can submit the post-trip report.');
            abort_unless($lockedTrip->status === 'approved', 409, 'Only approved trips can be reported.');
            abort_unless($lockedTrip->return_date->isBefore(today()), 409, 'Submit the post-trip report after the scheduled return date.');
            $lockedTrip->update([
                'status' => 'report_submitted',
                'actual_total' => $data['actual_total'],
                'post_trip_note' => trim($data['post_trip_note']),
                'report_submitted_at' => now(),
            ]);
            AuditTrail::record('travel.post_trip_report_submitted', $lockedTrip, [
                'actual_total' => $lockedTrip->actual_total,
                'currency' => $lockedTrip->currency,
            ]);

            return $lockedTrip;
        });

        return response()->json(['trip' => $this->decorateTrip(
            $trip->fresh()->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'reconciler:id,name']),
            $user,
        )]);
    }

    public function reconcile(Request $request, TravelRequest $trip): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canManagePeople(), 403, 'Only HR or administrators may reconcile travel reports.');
        $this->assertTripScope($user, $trip);
        abort_unless(PeopleScope::canReview($user, $trip->employee), 403);
        abort_if((int) $trip->submitted_by === (int) $user->id, 403, 'You cannot reconcile your own travel report.');
        abort_unless($trip->status === 'report_submitted', 409, 'Only submitted post-trip reports can be reconciled.');
        $data = $request->validate([
            'reconciliation_note' => ['required', 'string', 'max:2000'],
        ]);

        $trip = DB::transaction(function () use ($data, $trip, $user) {
            $lockedTrip = TravelRequest::query()->with('employee')->lockForUpdate()->findOrFail($trip->id);
            $this->assertTripScope($user, $lockedTrip);
            abort_unless(PeopleScope::canReview($user, $lockedTrip->employee), 403);
            abort_if((int) $lockedTrip->submitted_by === (int) $user->id, 403, 'You cannot reconcile your own travel report.');
            abort_unless($lockedTrip->status === 'report_submitted', 409, 'Only submitted post-trip reports can be reconciled.');
            $lockedTrip->update([
                'status' => 'reconciled',
                'reconciliation_note' => trim($data['reconciliation_note']),
                'reconciled_by' => $user->id,
                'reconciled_at' => now(),
            ]);
            AuditTrail::record('travel.report_reconciled', $lockedTrip, [
                'actual_total' => $lockedTrip->actual_total,
                'currency' => $lockedTrip->currency,
            ]);

            return $lockedTrip;
        });

        return response()->json(['trip' => $this->decorateTrip(
            $trip->fresh()->load(['employee:id,company_id,manager_id,user_id', 'reviewer:id,name', 'reconciler:id,name']),
            $user,
        )]);
    }

    private function decorateTrip(TravelRequest $trip, $user): TravelRequest
    {
        $trip->setAttribute('can_review', $trip->status === 'pending'
            && (int) $trip->submitted_by !== (int) $user->id
            && PeopleScope::canReview($user, $trip->employee));
        $trip->setAttribute('can_submit_report', $trip->status === 'approved'
            && (int) $trip->submitted_by === (int) $user->id
            && $trip->return_date->isBefore(today()));
        $trip->setAttribute('can_reconcile', $user->canManagePeople()
            && $trip->status === 'report_submitted'
            && (int) $trip->submitted_by !== (int) $user->id
            && PeopleScope::canReview($user, $trip->employee));

        return $trip;
    }

    private function travelFor($user): Builder
    {
        return TravelRequest::query()
            ->where('company_id', $user->company_id)
            ->whereIn('employee_id', PeopleScope::employees($user)->select('id'));
    }

    private function assertTripScope($user, TravelRequest $trip): void
    {
        abort_unless((int) $trip->company_id === (int) $user->company_id, 404);
        abort_unless(PeopleScope::employees($user)->whereKey($trip->employee_id)->exists(), 404);
    }

    private function ensureAccess($user): void
    {
        abort_unless(in_array($user->role, ['employee', 'manager', 'hr', 'admin'], true), 403, 'Travel requests are not available for this role.');
    }

    private function toCents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
