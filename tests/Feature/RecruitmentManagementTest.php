<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_open_requisition_add_candidates_and_advance_the_pipeline(): void
    {
        [$company, $branch, $department, $hr] = $this->recruitmentFixture();
        $this->actingAs($hr);

        $requisition = $this->postJson('/api/recruitment/requisitions', [
            'code' => 'REC-OPS-01',
            'title' => 'Operations Analyst',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employment_type' => 'Full-time',
            'work_mode' => 'Hybrid',
            'location' => 'Dubai',
            'openings' => 2,
            'priority' => 'high',
            'target_date' => now()->addMonth()->toDateString(),
            'description' => 'Support the operations team.',
        ])->assertCreated()
            ->assertJsonPath('requisition.status', 'open')
            ->assertJsonPath('requisition.department.name', 'Operations');
        $requisitionId = $requisition->json('requisition.id');

        $candidate = $this->postJson("/api/recruitment/requisitions/{$requisitionId}/candidates", [
            'first_name' => 'Amina',
            'last_name' => 'Rahman',
            'email' => ' AMINA.RAHMAN@example.test ',
            'source' => 'Referral',
            'rating' => 4,
            'notes' => 'Strong operations background.',
        ])->assertCreated()
            ->assertJsonPath('candidate.email', 'amina.rahman@example.test')
            ->assertJsonPath('candidate.stage', 'new');
        $candidateId = $candidate->json('candidate.id');

        $this->postJson("/api/recruitment/requisitions/{$requisitionId}/candidates", [
            'first_name' => 'Duplicate',
            'last_name' => 'Applicant',
            'email' => 'amina.rahman@example.test',
            'source' => 'Direct',
        ])->assertUnprocessable();

        $this->patchJson("/api/recruitment/candidates/{$candidateId}", [
            'stage' => 'interview',
            'rating' => 5,
            'notes' => 'Interview scheduled.',
        ])->assertOk()->assertJsonPath('candidate.stage', 'interview');

        $this->patchJson("/api/recruitment/candidates/{$candidateId}", [
            'stage' => 'interview',
            'rating' => 4,
            'notes' => 'Updated interviewer feedback.',
        ])->assertOk()
            ->assertJsonPath('candidate.rating', 4)
            ->assertJsonPath('candidate.notes', 'Updated interviewer feedback.');

        $this->patchJson("/api/recruitment/candidates/{$candidateId}", [
            'stage' => 'screening',
            'rating' => 3,
        ])->assertUnprocessable();

        $this->getJson('/api/recruitment')
            ->assertOk()
            ->assertJsonPath('metrics.open_requisitions', 1)
            ->assertJsonPath('metrics.active_candidates', 1)
            ->assertJsonPath('pipeline.interview', 1)
            ->assertJsonPath('candidate_pagination.total', 1)
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('requisitions.0.code', 'REC-OPS-01');

        $this->getJson('/api/recruitment?search=amina')
            ->assertOk()
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidate_pagination.total', 1);

        $this->assertDatabaseHas('recruitment_candidates', [
            'id' => $candidateId,
            'company_id' => $company->id,
            'stage' => 'interview',
        ]);
    }

    public function test_hiring_an_offer_creates_an_employee_and_fills_capacity_once(): void
    {
        [$company, $branch, $department, $hr] = $this->recruitmentFixture();
        $position = Position::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'title' => 'Operations Analyst',
            'code' => 'OPS-ANL-01',
            'employment_type' => 'Full-time',
            'headcount' => 1,
            'status' => 'open',
        ]);
        $requisition = RecruitmentRequisition::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'created_by' => $hr->id,
            'code' => 'REC-OPS-02',
            'title' => 'Operations Analyst',
            'employment_type' => 'Full-time',
            'work_mode' => 'On-site',
            'location' => 'Dubai',
            'openings' => 1,
            'priority' => 'normal',
            'status' => 'open',
        ]);
        $candidate = RecruitmentCandidate::create([
            'company_id' => $company->id,
            'requisition_id' => $requisition->id,
            'created_by' => $hr->id,
            'first_name' => 'Amina',
            'last_name' => 'Rahman',
            'email' => 'amina.hire@example.test',
            'source' => 'Referral',
            'stage' => 'new',
        ]);

        $this->actingAs($hr);
        $this->postJson("/api/recruitment/candidates/{$candidate->id}/hire", [
            'employee_code' => 'REC-EMP-01',
            'join_date' => now()->addWeek()->toDateString(),
        ])->assertStatus(409);

        $this->patchJson("/api/recruitment/candidates/{$candidate->id}", [
            'stage' => 'offer',
            'rating' => 5,
        ])->assertOk();

        $this->postJson("/api/recruitment/candidates/{$candidate->id}/hire", [
            'employee_code' => 'REC-EMP-01',
            'join_date' => now()->addWeek()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('employee.employee_code', 'REC-EMP-01')
            ->assertJsonPath('candidate.stage', 'hired');

        $this->assertDatabaseHas('employees', [
            'company_id' => $company->id,
            'employee_code' => 'REC-EMP-01',
            'work_email' => 'amina.hire@example.test',
            'position_id' => $position->id,
            'department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('recruitment_requisitions', ['id' => $requisition->id, 'status' => 'filled']);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'status' => 'filled']);

        $this->postJson("/api/recruitment/candidates/{$candidate->id}/hire", [
            'employee_code' => 'REC-EMP-02',
            'join_date' => now()->addWeek()->toDateString(),
        ])->assertStatus(409);
        $this->assertSame(1, Employee::query()->where('company_id', $company->id)->count());
    }

    public function test_recruitment_data_is_company_scoped_and_hr_only(): void
    {
        [$company, $branch, $department, $hr, $manager] = $this->recruitmentFixture();
        $foreignCompany = Company::create([
            'name' => 'Foreign Company',
            'code' => 'FOREIGN',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $foreignRequisition = RecruitmentRequisition::create([
            'company_id' => $foreignCompany->id,
            'code' => 'FOREIGN-01',
            'title' => 'Private role',
            'employment_type' => 'Full-time',
            'work_mode' => 'Remote',
            'openings' => 1,
            'priority' => 'normal',
            'status' => 'open',
        ]);
        $requisition = RecruitmentRequisition::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $hr->id,
            'code' => 'REC-SEC-01',
            'title' => 'Scoped role',
            'employment_type' => 'Full-time',
            'work_mode' => 'On-site',
            'openings' => 1,
            'priority' => 'normal',
            'status' => 'open',
        ]);

        $this->actingAs($manager)->getJson('/api/recruitment')->assertForbidden();

        $this->actingAs($hr)
            ->getJson('/api/recruitment')
            ->assertOk()
            ->assertJsonCount(1, 'requisitions')
            ->assertJsonPath('requisitions.0.code', $requisition->code);

        $this->actingAs($hr)
            ->postJson("/api/recruitment/requisitions/{$foreignRequisition->id}/candidates", [
                'first_name' => 'Private',
                'last_name' => 'Person',
                'email' => 'private@example.test',
                'source' => 'Direct',
            ])->assertNotFound();
    }

    private function recruitmentFixture(): array
    {
        $company = Company::create([
            'name' => 'Recruitment Test Company',
            'code' => 'RECRUIT',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'RECRUIT-MAIN',
            'city' => 'Dubai',
        ]);
        $department = Department::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Operations',
            'code' => 'OPS',
            'status' => 'active',
        ]);
        $hr = User::create([
            'name' => 'Recruitment HR',
            'email' => 'hr@recruitment.example.test',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'hr',
        ]);
        $manager = User::create([
            'name' => 'Recruitment Manager',
            'email' => 'manager@recruitment.example.test',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => 'manager',
        ]);

        return [$company, $branch, $department, $hr, $manager];
    }
}
