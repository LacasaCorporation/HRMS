<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TravelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_submits_a_scoped_future_trip_with_itinerary_notes(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$user, $employee] = $this->employee($company, $branch, 'TR-001', 'Avery', 'Traveler');
        [, $other] = $this->employee($company, $branch, 'TR-002', 'Other', 'Traveler');
        $this->createTrip($company, $branch, $other, $other->user);

        $start = today()->addDays(10)->toDateString();
        $end = today()->addDays(12)->toDateString();
        $this->actingAs($user)->postJson('/api/travel', [
            'origin' => 'Dubai',
            'destination' => 'Abu Dhabi',
            'purpose' => 'Invalid date range',
            'departure_date' => $start,
            'return_date' => today()->addDays(9)->toDateString(),
            'estimated_budget' => '750.50',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['return_date']);

        $this->postJson('/api/travel', [
            'origin' => 'Dubai',
            'destination' => 'Abu Dhabi',
            'purpose' => 'Past departure',
            'departure_date' => today()->subDay()->toDateString(),
            'return_date' => today()->toDateString(),
            'estimated_budget' => '750.50',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['departure_date']);

        $this->actingAs($user)->postJson('/api/travel', [
            'origin' => 'Dubai',
            'destination' => 'Abu Dhabi',
            'purpose' => 'Customer planning workshop',
            'departure_date' => $start,
            'return_date' => $end,
            'estimated_budget' => '750.50',
            'itinerary_notes' => 'Outbound morning train; return after the final meeting.',
        ])->assertCreated()
            ->assertJsonPath('trip.employee_id', $employee->id)
            ->assertJsonPath('trip.status', 'pending')
            ->assertJsonPath('trip.currency', 'AED')
            ->assertJsonPath('trip.itinerary_notes', 'Outbound morning train; return after the final meeting.');

        $this->getJson('/api/travel')
            ->assertOk()
            ->assertJsonCount(1, 'trips')
            ->assertJsonPath('trips.0.employee_id', $employee->id)
            ->assertJsonPath('meta.pending', 1);
    }

    public function test_manager_approves_a_direct_report_within_estimate_or_rejects_with_a_reason(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$managerUser, $manager] = $this->employee($company, $branch, 'TR-MGR', 'Morgan', 'Manager', 'manager');
        [$travelerUser, $traveler] = $this->employee($company, $branch, 'TR-REP', 'Riley', 'Report');
        $traveler->update(['manager_id' => $manager->id]);
        [, $unrelated] = $this->employee($company, $branch, 'TR-OTH', 'Una', 'Other');
        $trip = $this->createTrip($company, $branch, $traveler, $travelerUser, '1000.00');
        $unrelatedTrip = $this->createTrip($company, $branch, $unrelated, $unrelated->user);

        $this->actingAs($managerUser)->getJson('/api/travel')
            ->assertOk()
            ->assertJsonCount(1, 'trips')
            ->assertJsonFragment(['can_review' => true]);
        $this->postJson("/api/travel/{$trip->id}/review", [
            'decision' => 'approve',
            'approved_budget' => '900.00',
            'review_note' => 'Within the approved estimate.',
        ])->assertOk()
            ->assertJsonPath('trip.status', 'approved')
            ->assertJsonPath('trip.approved_budget', '900.00');
        $this->postJson("/api/travel/{$unrelatedTrip->id}/review", [
            'decision' => 'reject',
            'review_note' => 'Not in my reporting scope.',
        ])->assertNotFound();

        $secondTrip = $this->createTrip($company, $branch, $traveler, $travelerUser, '500.00');
        $this->postJson("/api/travel/{$secondTrip->id}/review", [
            'decision' => 'approve',
            'approved_budget' => '500.01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['approved_budget']);
        $this->postJson("/api/travel/{$secondTrip->id}/review", [
            'decision' => 'reject',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['review_note']);
        $this->postJson("/api/travel/{$secondTrip->id}/review", [
            'decision' => 'reject',
            'review_note' => 'Business dates are not available.',
        ])->assertOk()
            ->assertJsonPath('trip.status', 'rejected');
    }

    public function test_traveler_submits_post_trip_report_and_hr_reconciles_it_without_payment(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hrUser] = $this->employee($company, $branch, 'TR-HR', 'Harper', 'HR', 'hr');
        [$travelerUser, $traveler] = $this->employee($company, $branch, 'TR-EMP', 'Evan', 'Traveler');
        $trip = $this->createTrip($company, $branch, $traveler, $travelerUser, '400.00', 'approved', today()->subDays(3), today()->subDay());
        $trip->update([
            'approved_budget' => '350.00',
            'reviewed_by' => $hrUser->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($travelerUser)->postJson("/api/travel/{$trip->id}/reconcile", [
            'reconciliation_note' => 'Unauthorized.',
        ])->assertForbidden();
        $this->postJson("/api/travel/{$trip->id}/post-trip-report", [
            'actual_total' => '425.00',
            'post_trip_note' => 'Workshop completed. Higher actual includes a late local transport change.',
        ])->assertOk()
            ->assertJsonPath('trip.status', 'report_submitted')
            ->assertJsonPath('trip.actual_total', '425.00')
            ->assertJsonPath('trip.can_reconcile', true);

        $this->actingAs($hrUser)->postJson("/api/travel/{$trip->id}/reconcile", [
            'reconciliation_note' => 'Reviewed the explanation and actual total against the approved ceiling.',
        ])->assertOk()
            ->assertJsonPath('trip.status', 'reconciled')
            ->assertJsonPath('trip.actual_total', '425.00')
            ->assertJsonPath('trip.reconciliation_note', 'Reviewed the explanation and actual total against the approved ceiling.');
        $this->postJson("/api/travel/{$trip->id}/reconcile", [
            'reconciliation_note' => 'Duplicate reconciliation.',
        ])->assertStatus(409);
    }

    public function test_post_trip_report_is_blocked_until_return_date_and_employee_cannot_review_own_request(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$user, $employee] = $this->employee($company, $branch, 'TR-EMP', 'Evan', 'Traveler', 'hr');
        $trip = $this->createTrip($company, $branch, $employee, $user, '400.00', 'approved');
        $trip->update(['approved_budget' => '400.00']);

        $this->actingAs($user)->postJson("/api/travel/{$trip->id}/review", [
            'decision' => 'approve',
            'approved_budget' => '400.00',
        ])->assertForbidden();
        $this->postJson("/api/travel/{$trip->id}/post-trip-report", [
            'actual_total' => '100.00',
            'post_trip_note' => 'Trip not yet completed.',
        ])->assertStatus(409);

        $returningToday = $this->createTrip($company, $branch, $employee, $user, '200.00', 'approved', today()->subDays(3), today());
        $returningToday->update(['approved_budget' => '200.00']);
        $this->postJson("/api/travel/{$returningToday->id}/post-trip-report", [
            'actual_total' => '100.00',
            'post_trip_note' => 'The scheduled return date is today.',
        ])->assertStatus(409);
    }

    public function test_branch_hr_cannot_view_or_reconcile_other_branch_trips(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hrUser] = $this->employee($company, $branch, 'TR-HR', 'Harper', 'HR', 'hr');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Branch',
            'code' => 'TR-02',
            'city' => 'Abu Dhabi',
        ]);
        [$travelerUser, $traveler] = $this->employee($company, $otherBranch, 'TR-EMP', 'Evan', 'Traveler');
        $trip = $this->createTrip($company, $otherBranch, $traveler, $travelerUser, '400.00', 'report_submitted', today()->subDays(4), today()->subDay());

        $this->actingAs($hrUser)->getJson('/api/travel')
            ->assertOk()
            ->assertJsonCount(0, 'trips');
        $this->postJson("/api/travel/{$trip->id}/reconcile", [
            'reconciliation_note' => 'Out of scope.',
        ])->assertNotFound();
    }

    private function companyAndBranch(): array
    {
        $company = Company::create(['name' => 'Travel Company', 'code' => 'TRVL', 'country' => 'AE', 'currency' => 'AED']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'Main Branch', 'code' => 'TR-01', 'city' => 'Dubai']);

        return [$company, $branch];
    }

    private function employee(Company $company, Branch $branch, string $code, string $first, string $last, string $role = 'employee'): array
    {
        $user = User::create([
            'name' => "{$first} {$last}",
            'email' => strtolower($code).'@travel.test',
            'password' => 'test-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'employee_code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'department' => 'Operations',
            'currency' => 'AED',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function createTrip(
        Company $company,
        Branch $branch,
        Employee $employee,
        User $submitter,
        string $estimate = '500.00',
        string $status = 'pending',
        $departure = null,
        $return = null,
    ): TravelRequest {
        $departure ??= today()->addDays(20);
        $return ??= today()->addDays(22);

        return TravelRequest::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'submitted_by' => $submitter->id,
            'employee_code' => $employee->employee_code,
            'employee_name' => trim($employee->first_name.' '.$employee->last_name),
            'department' => $employee->department,
            'origin' => 'Dubai',
            'destination' => 'Abu Dhabi',
            'purpose' => 'Travel workflow test',
            'departure_date' => $departure,
            'return_date' => $return,
            'currency' => 'AED',
            'estimated_budget' => $estimate,
            'status' => $status,
            'actual_total' => $status === 'report_submitted' ? '425.00' : null,
            'post_trip_note' => $status === 'report_submitted' ? 'Travel report explanation.' : null,
            'report_submitted_at' => $status === 'report_submitted' ? now() : null,
        ]);
    }
}
