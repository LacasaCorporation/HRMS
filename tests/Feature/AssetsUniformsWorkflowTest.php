<?php

namespace Tests\Feature;

use App\Models\AssetAssignment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyAsset;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetsUniformsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_moves_through_handover_acknowledgement_return_and_maintenance(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'asset-hr@test.example', 'hr');
        [$employeeUser, $employee] = $this->person($company, $branch, 'asset-employee@test.example', 'employee');

        $asset = $this->actingAs($hr)->postJson('/api/assets-uniforms/assets', [
            'asset_tag' => ' PPE-001 ',
            'name' => 'Protective helmet',
            'category' => 'ppe',
            'serial_number' => 'H-001',
            'condition' => 'new',
            'notes' => 'Blue helmet, size M.',
        ])->assertCreated()
            ->assertJsonPath('asset.asset_tag', 'PPE-001')
            ->assertJsonPath('asset.status', 'available')
            ->json('asset');

        $this->postJson('/api/assets-uniforms/assets', [
            'asset_tag' => 'PPE-001',
            'name' => 'Duplicate tag',
            'category' => 'ppe',
            'condition' => 'new',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['asset_tag']);

        $assignment = $this->postJson("/api/assets-uniforms/assets/{$asset['id']}/assign", [
            'employee_id' => $employee->id,
        ])->assertCreated()
            ->assertJsonPath('assignment.status', 'pending_acknowledgement')
            ->assertJsonPath('assignment.employee.id', $employee->id)
            ->json('assignment');

        $this->getJson('/api/assets-uniforms')
            ->assertOk()
            ->assertJsonPath('meta.pending_acknowledgement', 1)
            ->assertJsonPath('assets.0.status', 'pending_acknowledgement');

        $this->actingAs($employeeUser)->getJson('/api/assets-uniforms')
            ->assertOk()
            ->assertJsonPath('view', 'my_assets')
            ->assertJsonCount(0, 'assets')
            ->assertJsonPath('assignments.0.id', $assignment['id'])
            ->assertJsonPath('meta.pending_acknowledgement', 1);

        $this->postJson("/api/assets-uniforms/assignments/{$assignment['id']}/acknowledge", [
            'decision' => 'accept',
            'note' => 'Received in the listed condition.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'assigned')
            ->assertJsonPath('assignment.acknowledger.id', $employeeUser->id);

        $this->actingAs($hr)->postJson("/api/assets-uniforms/assignments/{$assignment['id']}/return", [
            'return_condition' => 'damaged',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['return_notes']);

        $this->postJson("/api/assets-uniforms/assignments/{$assignment['id']}/return", [
            'return_condition' => 'damaged',
            'return_notes' => 'Strap is cracked; item needs inspection.',
        ])->assertOk()
            ->assertJsonPath('assignment.status', 'returned')
            ->assertJsonPath('assignment.return_condition', 'damaged');

        $this->assertDatabaseHas('company_assets', [
            'id' => $asset['id'],
            'status' => 'maintenance',
            'condition' => 'damaged',
        ]);
        $this->postJson("/api/assets-uniforms/assets/{$asset['id']}/maintenance-complete", [
            'condition' => 'good',
            'maintenance_note' => 'Replaced strap and inspected before release.',
        ])->assertOk()
            ->assertJsonPath('asset.status', 'available')
            ->assertJsonPath('asset.condition', 'good');

        $this->assertDatabaseHas('asset_assignments', [
            'id' => $assignment['id'],
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'returned',
            'return_condition' => 'damaged',
        ]);
        $this->assertDatabaseHas('company_assets', [
            'id' => $asset['id'],
            'status' => 'available',
            'maintenance_note' => 'Replaced strap and inspected before release.',
        ]);
    }

    public function test_employee_must_give_reason_to_decline_and_declined_item_returns_to_inventory(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'decline-hr@test.example', 'hr');
        [$employeeUser, $employee] = $this->person($company, $branch, 'decline-employee@test.example', 'employee');
        $asset = $this->asset($company, $branch, $hr, 'UNIFORM-01');

        $assignment = $this->actingAs($hr)->postJson("/api/assets-uniforms/assets/{$asset->id}/assign", [
            'employee_id' => $employee->id,
        ])->assertCreated()->json('assignment');

        $this->actingAs($employeeUser)->postJson("/api/assets-uniforms/assignments/{$assignment['id']}/acknowledge", [
            'decision' => 'decline',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);

        $this->postJson("/api/assets-uniforms/assignments/{$assignment['id']}/acknowledge", [
            'decision' => 'decline',
            'note' => 'The size provided does not fit.',
        ])->assertOk()->assertJsonPath('assignment.status', 'declined');

        $this->assertDatabaseHas('company_assets', ['id' => $asset->id, 'status' => 'available']);
        $this->assertDatabaseHas('asset_assignments', [
            'id' => $assignment['id'],
            'status' => 'declined',
            'acknowledgement_note' => 'The size provided does not fit.',
        ]);
    }

    public function test_inventory_and_handover_actions_enforce_role_tenant_and_branch_scope(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'scope-asset-hr@test.example', 'hr');
        [$manager] = $this->person($company, $branch, 'scope-asset-manager@test.example', 'manager');
        [, $employee] = $this->person($company, $branch, 'scope-asset-employee@test.example', 'employee');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Site',
            'code' => 'ASSET-OTHER',
        ]);
        [, $otherEmployee] = $this->person($company, $otherBranch, 'scope-other-employee@test.example', 'employee');
        $otherAsset = $this->asset($company, $otherBranch, $hr, 'OTHER-001');
        $otherCompany = Company::create([
            'name' => 'Other Company',
            'code' => 'OTHER-ASSET',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $otherCompanyBranch = Branch::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Site',
            'code' => 'OTHER-ASSET-BR',
        ]);
        [$otherHr] = $this->person($otherCompany, $otherCompanyBranch, 'other-company-hr@test.example', 'hr');
        $foreignAsset = $this->asset($otherCompany, $otherCompanyBranch, $otherHr, 'FOREIGN-001');

        $this->actingAs($manager)->getJson('/api/assets-uniforms')->assertForbidden();
        $this->postJson('/api/assets-uniforms/assets', [
            'asset_tag' => 'NOPE',
            'name' => 'Unauthorized',
            'category' => 'other',
            'condition' => 'good',
        ])->assertForbidden();

        $branchAssignment = AssetAssignment::create([
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'asset_id' => $otherAsset->id,
            'employee_id' => $otherEmployee->id,
            'assigned_by' => $hr->id,
            'status' => 'pending_acknowledgement',
            'condition_at_issue' => 'good',
            'assigned_at' => now(),
        ]);

        $this->actingAs($hr)->getJson('/api/assets-uniforms')
            ->assertOk()
            ->assertJsonCount(0, 'assets')
            ->assertJsonCount(3, 'employees')
            ->assertJsonMissing(['id' => $otherEmployee->id]);
        $this->postJson("/api/assets-uniforms/assets/{$otherAsset->id}/assign", [
            'employee_id' => $employee->id,
        ])->assertNotFound();
        $this->postJson("/api/assets-uniforms/assets/{$foreignAsset->id}/assign", [
            'employee_id' => $employee->id,
        ])->assertNotFound();
        $this->postJson("/api/assets-uniforms/assignments/{$branchAssignment->id}/return", [
            'return_condition' => 'good',
        ])->assertNotFound();

        $this->actingAs($otherEmployee->user)
            ->postJson("/api/assets-uniforms/assignments/{$branchAssignment->id}/acknowledge", [
                'decision' => 'accept',
            ])->assertOk();
    }

    public function test_company_wide_inventory_takes_the_assignee_branch_scope_at_handover(): void
    {
        [$company, $branch] = $this->companyAndBranch();
        [$branchHr] = $this->person($company, $branch, 'branch-asset-hr@test.example', 'hr');
        [, $employee] = $this->person($company, $branch, 'branch-asset-employee@test.example', 'employee');
        $companyAdmin = User::create([
            'name' => 'Company Administrator',
            'email' => 'company-asset-admin@test.example',
            'password' => 'secret',
            'company_id' => $company->id,
            'branch_id' => null,
            'role' => 'admin',
        ]);
        $asset = CompanyAsset::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'asset_tag' => 'SHARED-001',
            'name' => 'Shared laptop',
            'category' => 'equipment',
            'condition' => 'good',
            'status' => 'available',
            'created_by' => $companyAdmin->id,
        ]);

        $assignment = $this->actingAs($companyAdmin)->postJson("/api/assets-uniforms/assets/{$asset->id}/assign", [
            'employee_id' => $employee->id,
        ])->assertCreated()
            ->assertJsonPath('assignment.branch_id', $branch->id)
            ->json('assignment');

        $this->assertDatabaseHas('company_assets', [
            'id' => $asset->id,
            'branch_id' => $branch->id,
            'status' => 'pending_acknowledgement',
        ]);
        $this->actingAs($branchHr)->getJson('/api/assets-uniforms')
            ->assertOk()
            ->assertJsonPath('assets.0.id', $asset->id)
            ->assertJsonPath('assignments.0.id', $assignment['id']);
    }

    private function companyAndBranch(): array
    {
        $company = Company::create([
            'name' => 'Asset Test Company',
            'code' => 'ASSET',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Site',
            'code' => 'ASSET-MAIN',
        ]);

        return [$company, $branch];
    }

    private function person(Company $company, Branch $branch, string $email, string $role): array
    {
        $name = ucfirst(strtok($email, '@'));
        $user = User::create([
            'name' => $name,
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
            'employee_code' => strtoupper(substr(hash('sha256', $email), 0, 8)),
            'first_name' => $name,
            'last_name' => 'Person',
            'department' => 'Operations',
            'status' => 'active',
        ]);

        return [$user, $employee];
    }

    private function asset(Company $company, Branch $branch, User $creator, string $tag): CompanyAsset
    {
        return CompanyAsset::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'asset_tag' => $tag,
            'name' => "Item {$tag}",
            'category' => 'equipment',
            'condition' => 'good',
            'status' => 'available',
            'created_by' => $creator->id,
        ]);
    }
}
