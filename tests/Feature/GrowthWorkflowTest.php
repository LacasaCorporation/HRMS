<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_complete_and_close_a_performance_review_cycle(): void
    {
        [$company, $branch, $hr] = $this->createPeopleContext();
        $firstEmployee = $this->createEmployee($company, $branch, 'GROW-001', 'Taylor');
        $secondEmployee = $this->createEmployee($company, $branch, 'GROW-002', 'Morgan');
        $this->actingAs($hr);

        $cycle = $this->postJson('/api/performance/cycles', [
            'title' => 'Annual development review',
            'period_start' => today()->subMonths(12)->toDateString(),
            'period_end' => today()->toDateString(),
            'due_date' => today()->addDays(14)->toDateString(),
            'description' => 'Document goals and results.',
            'employee_ids' => [$firstEmployee->id, $secondEmployee->id],
        ])->assertCreated()
            ->assertJsonPath('cycle.status', 'open')
            ->assertJsonPath('cycle.reviews_count', 2)
            ->json('cycle');
        $this->assertDatabaseCount('performance_reviews', 2);

        $reviewIds = \App\Models\PerformanceReview::query()
            ->where('performance_cycle_id', $cycle['id'])
            ->orderBy('employee_code')
            ->pluck('id')
            ->all();
        $firstReviewId = $reviewIds[0];
        $secondReviewId = $reviewIds[1];

        $this->patchJson("/api/performance/cycles/{$cycle['id']}/status", ['status' => 'closed'])
            ->assertStatus(409);
        $this->postJson("/api/performance/reviews/{$firstReviewId}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');

        $this->patchJson("/api/performance/reviews/{$firstReviewId}", [
            'rating' => 4,
            'goals' => 'Improve onboarding documentation.',
            'results' => 'Completed the first draft and gathered feedback.',
            'strengths' => 'Cross-team collaboration.',
            'development_focus' => 'Delegation.',
        ])->assertOk()
            ->assertJsonPath('review.rating', 4);
        $this->postJson("/api/performance/reviews/{$firstReviewId}/submit")
            ->assertOk()
            ->assertJsonPath('review.status', 'in_review');
        $this->patchJson("/api/performance/reviews/{$firstReviewId}", ['rating' => 5])
            ->assertStatus(409);
        $this->postJson("/api/performance/reviews/{$firstReviewId}/review", [
            'decision' => 'return',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('review_note');
        $this->postJson("/api/performance/reviews/{$firstReviewId}/review", [
            'decision' => 'return',
            'review_note' => 'Please clarify the outcome measures.',
        ])->assertOk()
            ->assertJsonPath('review.status', 'changes_requested');
        $this->patchJson("/api/performance/reviews/{$firstReviewId}", [
            'results' => 'Completed the draft and incorporated feedback from three teams.',
        ])->assertOk();
        $this->postJson("/api/performance/reviews/{$firstReviewId}/submit")
            ->assertOk()
            ->assertJsonPath('review.status', 'in_review');
        $this->postJson("/api/performance/reviews/{$firstReviewId}/review", [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('review.status', 'approved');

        $this->patchJson("/api/performance/reviews/{$secondReviewId}", [
            'rating' => 3,
            'goals' => 'Complete safety training.',
            'results' => 'Completed the scheduled sessions.',
        ])->assertOk();
        $this->postJson("/api/performance/reviews/{$secondReviewId}/submit")
            ->assertOk()
            ->assertJsonPath('review.status', 'in_review');
        $this->postJson("/api/performance/reviews/{$secondReviewId}/review", [
            'decision' => 'approve',
        ])->assertOk()
            ->assertJsonPath('review.status', 'approved');

        $this->patchJson("/api/performance/cycles/{$cycle['id']}/status", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('cycle.status', 'closed');
        $this->patchJson("/api/performance/reviews/{$firstReviewId}", ['rating' => 2])
            ->assertStatus(409);
        $this->getJson('/api/performance')
            ->assertOk()
            ->assertJsonPath('meta.reviews_approved', 2)
            ->assertJsonPath('cycles.0.status', 'closed');
    }

    public function test_branch_hr_sees_only_branch_reviews_and_cannot_change_company_wide_cycles(): void
    {
        [$company, $branch, $hr] = $this->createPeopleContext();
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'North Branch',
            'code' => 'GROW-NORTH',
        ]);
        $branchEmployee = $this->createEmployee($company, $branch, 'GROW-101', 'Avery');
        $otherBranchEmployee = $this->createEmployee($company, $otherBranch, 'GROW-102', 'Jordan');
        $branchHr = User::create([
            'name' => 'Branch HR',
            'email' => 'branch.hr@growth.example.test',
            'password' => 'branch-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $cycle = $this->actingAs($hr)->postJson('/api/performance/cycles', [
            'title' => 'Company-wide cycle',
            'period_start' => today()->subMonth()->toDateString(),
            'period_end' => today()->toDateString(),
            'employee_ids' => [$branchEmployee->id, $otherBranchEmployee->id],
        ])->assertCreated()->json('cycle');
        $otherReviewId = \App\Models\PerformanceReview::query()
            ->where('performance_cycle_id', $cycle['id'])
            ->where('employee_id', $otherBranchEmployee->id)
            ->value('id');

        $this->actingAs($branchHr)->getJson('/api/performance')
            ->assertOk()
            ->assertJsonCount(1, 'reviews')
            ->assertJsonPath('cycles.0.reviews_count', 1);
        $this->patchJson("/api/performance/cycles/{$cycle['id']}/status", ['status' => 'closed'])
            ->assertForbidden();
        $this->patchJson("/api/performance/reviews/{$otherReviewId}", ['rating' => 3])
            ->assertNotFound();
    }

    public function test_hr_can_manage_training_capacity_enrollments_and_allowed_transitions(): void
    {
        [$company, $branch, $hr] = $this->createPeopleContext();
        $firstEmployee = $this->createEmployee($company, $branch, 'GROW-201', 'Taylor');
        $secondEmployee = $this->createEmployee($company, $branch, 'GROW-202', 'Morgan');
        $thirdEmployee = $this->createEmployee($company, $branch, 'GROW-203', 'Riley');
        $this->actingAs($hr);

        $program = $this->postJson('/api/training/programs', [
            'title' => 'Workplace safety essentials',
            'category' => 'safety',
            'provider' => 'Internal learning team',
            'delivery_mode' => 'blended',
            'start_date' => today()->toDateString(),
            'due_date' => today()->addMonth()->toDateString(),
            'capacity' => 2,
            'branch_id' => $branch->id,
        ])->assertCreated()
            ->assertJsonPath('program.status', 'active')
            ->json('program');

        $firstEnrollment = $this->postJson('/api/training/enrollments', [
            'training_program_id' => $program['id'],
            'employee_id' => $firstEmployee->id,
            'notes' => 'Assigned by HR.',
        ])->assertCreated()
            ->assertJsonPath('enrollment.status', 'assigned')
            ->json('enrollment');
        $this->postJson('/api/training/enrollments', [
            'training_program_id' => $program['id'],
            'employee_id' => $firstEmployee->id,
        ])->assertStatus(409);
        $secondEnrollment = $this->postJson('/api/training/enrollments', [
            'training_program_id' => $program['id'],
            'employee_id' => $secondEmployee->id,
        ])->assertCreated()->json('enrollment');
        $this->postJson('/api/training/enrollments', [
            'training_program_id' => $program['id'],
            'employee_id' => $thirdEmployee->id,
        ])->assertStatus(409);
        $this->patchJson("/api/training/programs/{$program['id']}", ['capacity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');

        $this->patchJson("/api/training/enrollments/{$firstEnrollment['id']}", [
            'status' => 'in_progress',
            'notes' => 'Started the first session.',
        ])->assertOk()
            ->assertJsonPath('enrollment.status', 'in_progress');
        $completed = $this->patchJson("/api/training/enrollments/{$firstEnrollment['id']}", [
            'status' => 'completed',
            'notes' => 'Completion manually confirmed.',
        ])->assertOk()
            ->assertJsonPath('enrollment.status', 'completed')
            ->json('enrollment');
        $this->assertNotNull($completed['completed_at']);
        $this->patchJson("/api/training/enrollments/{$firstEnrollment['id']}", [
            'status' => 'in_progress',
        ])->assertStatus(409);
        $this->patchJson("/api/training/enrollments/{$secondEnrollment['id']}", [
            'status' => 'cancelled',
        ])->assertOk()
            ->assertJsonPath('enrollment.status', 'cancelled');

        $this->getJson('/api/training')
            ->assertOk()
            ->assertJsonPath('meta.active_programs', 1)
            ->assertJsonPath('meta.completed_enrollments', 1)
            ->assertJsonPath('meta.active_enrollments', 0);
    }

    public function test_company_wide_training_program_is_read_only_for_branch_hr_and_growth_is_hr_only(): void
    {
        [$company, $branch, $hr] = $this->createPeopleContext();
        $employee = $this->createEmployee($company, $branch, 'GROW-301', 'Casey');
        $branchHr = User::create([
            'name' => 'Branch HR',
            'email' => 'branch.training@growth.example.test',
            'password' => 'branch-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $employeeUser = User::create([
            'name' => 'Employee User',
            'email' => 'employee@growth.example.test',
            'password' => 'employee-password',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'employee',
        ]);
        $program = $this->actingAs($hr)->postJson('/api/training/programs', [
            'title' => 'Professional development',
            'category' => 'professional',
            'delivery_mode' => 'self_paced',
        ])->assertCreated()->json('program');

        $this->actingAs($branchHr)->getJson('/api/training')
            ->assertOk()
            ->assertJsonCount(1, 'programs')
            ->assertJsonPath('programs.0.branch_id', null)
            ->assertJsonCount(1, 'employees');
        $this->patchJson("/api/training/programs/{$program['id']}", ['title' => 'Changed'])
            ->assertForbidden();
        $this->postJson('/api/training/enrollments', [
            'training_program_id' => $program['id'],
            'employee_id' => $employee->id,
        ])->assertCreated();
        $this->actingAs($employeeUser)->getJson('/api/training')->assertForbidden();
        $this->getJson('/api/performance')->assertForbidden();
    }

    private function createPeopleContext(): array
    {
        $company = Company::create([
            'name' => 'Growth Company',
            'code' => 'GROW',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'GROW-MAIN',
        ]);
        $hr = User::create([
            'name' => 'Growth HR',
            'email' => 'hr@growth.example.test',
            'password' => 'growth-password',
            'company_id' => $company->id,
            'role' => 'hr',
        ]);

        return [$company, $branch, $hr];
    }

    private function createEmployee(Company $company, Branch $branch, string $code, string $firstName): Employee
    {
        return Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_code' => $code,
            'first_name' => $firstName,
            'last_name' => 'Employee',
            'department' => 'Operations',
            'status' => 'active',
        ]);
    }
}
