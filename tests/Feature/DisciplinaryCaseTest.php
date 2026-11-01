<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisciplinaryCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_records_a_case_and_moves_it_through_investigation_response_and_resolution(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'hr@discipline.test', 'hr');
        [$investigator] = $this->person($company, $branch, 'investigator@discipline.test', 'admin');
        [, $employee] = $this->person($company, $branch, 'employee@discipline.test', 'employee');
        $this->actingAs($hr);

        $case = $this->postJson('/api/disciplinary/cases', [
            'employee_id' => $employee->id,
            'case_type' => 'conduct',
            'incident_date' => today()->subDays(2)->toDateString(),
            'summary' => 'A reported concern requiring a neutral review.',
        ])->assertCreated()
            ->assertJsonPath('case.status', 'open')
            ->assertJsonPath('case.case_type', 'conduct')
            ->assertJsonPath('case.employee.id', $employee->id)
            ->json('case');

        $this->getJson('/api/disciplinary')
            ->assertOk()
            ->assertJsonCount(1, 'cases')
            ->assertJsonPath('meta.open', 1)
            ->assertJsonFragment(['id' => $investigator->id, 'name' => $investigator->name]);

        $this->postJson("/api/disciplinary/cases/{$case['id']}/start-review", [
            'investigator_id' => $investigator->id,
            'investigation_notes' => 'Review the account and follow up with the parties involved.',
        ])->assertOk()
            ->assertJsonPath('case.status', 'under_review')
            ->assertJsonPath('case.investigator.id', $investigator->id);

        $this->postJson("/api/disciplinary/cases/{$case['id']}/employee-response", [
            'employee_response' => 'The employee response was recorded as received.',
        ])->assertOk()
            ->assertJsonPath('case.employee_response', 'The employee response was recorded as received.');

        $this->postJson("/api/disciplinary/cases/{$case['id']}/resolve", [
            'resolution_summary' => 'The review was completed and the documented next steps were shared.',
        ])->assertOk()
            ->assertJsonPath('case.status', 'resolved')
            ->assertJsonPath('case.resolution_summary', 'The review was completed and the documented next steps were shared.')
            ->assertJsonPath('case.resolver.id', $hr->id);

        $this->postJson("/api/disciplinary/cases/{$case['id']}/resolve", [
            'resolution_summary' => 'Attempt a second resolution.',
        ])->assertStatus(409);
        $this->assertDatabaseHas('disciplinary_cases', [
            'id' => $case['id'],
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'resolved',
        ]);
    }

    public function test_case_intake_validates_employee_category_and_past_incident_date(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'hr@discipline.test', 'hr');
        [, $employee] = $this->person($company, $branch, 'employee@discipline.test', 'employee');
        $this->actingAs($hr);

        $this->postJson('/api/disciplinary/cases', [
            'employee_id' => $employee->id,
            'case_type' => 'invalid',
            'incident_date' => today()->addDay()->toDateString(),
            'summary' => 'Invalid intake.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['case_type', 'incident_date']);

        $inactive = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_code' => 'DISC-OLD',
            'first_name' => 'Former',
            'last_name' => 'Employee',
            'status' => 'inactive',
        ]);
        $this->postJson('/api/disciplinary/cases', [
            'employee_id' => $inactive->id,
            'case_type' => 'policy',
            'incident_date' => today()->subDay()->toDateString(),
            'summary' => 'Historical concern.',
        ])->assertUnprocessable();
    }

    public function test_non_hr_roles_cannot_access_restricted_case_records_or_actions(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'hr@discipline.test', 'hr');
        [$manager] = $this->person($company, $branch, 'manager@discipline.test', 'manager');
        [, $employee] = $this->person($company, $branch, 'employee@discipline.test', 'employee');
        $case = DisciplinaryCase::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'created_by' => $hr->id,
            'case_type' => 'grievance',
            'incident_date' => today()->subDay(),
            'summary' => 'Restricted test record.',
            'status' => 'open',
        ]);

        $this->actingAs($manager)->getJson('/api/disciplinary')->assertForbidden();
        $this->postJson("/api/disciplinary/cases/{$case->id}/start-review", [
            'investigator_id' => $hr->id,
        ])->assertForbidden();
        $this->actingAs($employee->user)->getJson('/api/disciplinary')->assertForbidden();
    }

    public function test_branch_hr_cannot_access_cases_from_another_branch_or_company(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'hr@discipline.test', 'hr');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Branch',
            'code' => 'DISC-OTHER',
        ]);
        [$otherHr] = $this->person($company, $otherBranch, 'other-hr@discipline.test', 'hr');
        [, $employee] = $this->person($company, $otherBranch, 'other-employee@discipline.test', 'employee');
        $case = DisciplinaryCase::create([
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'employee_id' => $employee->id,
            'created_by' => $otherHr->id,
            'case_type' => 'attendance',
            'incident_date' => today()->subDay(),
            'summary' => 'Other branch record.',
            'status' => 'open',
        ]);

        $this->actingAs($hr)->getJson('/api/disciplinary')
            ->assertOk()
            ->assertJsonCount(0, 'cases');
        $this->postJson("/api/disciplinary/cases/{$case->id}/start-review", [
            'investigator_id' => $hr->id,
        ])->assertNotFound();
    }

    private function companyAndBranch(): array
    {
        $company = Company::create([
            'name' => 'Disciplinary Test Company',
            'code' => 'DISC',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'code' => 'DISC-MAIN',
        ]);

        return [$company, $branch];
    }

    private function person(Company $company, Branch $branch, string $email, string $role): array
    {
        $user = User::create([
            'name' => ucfirst(strtok($email, '@')),
            'email' => $email,
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'employee_code' => strtoupper(substr(strtok($email, '@'), 0, 8)),
            'first_name' => $user->name,
            'last_name' => 'Person',
            'department' => 'Operations',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }
}
