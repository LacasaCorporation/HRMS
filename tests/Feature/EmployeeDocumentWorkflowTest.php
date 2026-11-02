<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_upload_private_document_versions_and_employee_can_download_only_their_own(): void
    {
        Storage::fake('local');
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'documents-hr@test.example', 'hr');
        [$employeeUser, $employee] = $this->person($company, $branch, 'documents-employee@test.example', 'employee');

        $created = $this->actingAs($hr)->post('/api/documents', [
            'employee_id' => $employee->id,
            'category' => 'visa',
            'title' => 'Work authorization',
            'issued_on' => today()->subYear()->toDateString(),
            'expires_on' => today()->addDays(20)->toDateString(),
            'notes' => 'Renewal in progress.',
            'file' => UploadedFile::fake()->create('work-authorization.pdf', 32, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('document.title', 'Work authorization')
            ->assertJsonPath('document.expiry_status', 'expiring_soon')
            ->assertJsonPath('document.versions.0.version_number', 1)
            ->assertJsonPath('document.versions.0.original_name', 'work-authorization.pdf')
            ->assertJsonMissingPath('document.versions.0.file_path');
        $documentId = $created->json('document.id');
        $firstVersionId = $created->json('document.versions.0.id');
        $firstPath = EmployeeDocumentVersion::findOrFail($firstVersionId)->file_path;
        Storage::disk('local')->assertExists($firstPath);

        $this->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.expiring_soon', 1)
            ->assertJsonPath('documents.0.employee.id', $employee->id);

        $this->post("/api/documents/{$documentId}/versions", [
            'category' => 'visa',
            'title' => 'Work authorization',
            'issued_on' => today()->toDateString(),
            'expires_on' => today()->addYear()->toDateString(),
            'notes' => 'Renewed document.',
            'file' => UploadedFile::fake()->create('renewed-authorization.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('document.title', 'Work authorization')
            ->assertJsonPath('document.notes', 'Renewal in progress.')
            ->assertJsonPath('document.expiry_status', 'valid')
            ->assertJsonPath('document.versions.0.version_number', 2)
            ->assertJsonPath('document.versions.1.version_number', 1);

        $this->actingAs($employeeUser)->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(1, 'documents')
            ->assertJsonPath('meta.can_manage', false);
        $this->get('/api/documents/'.$documentId.'/versions/'.$firstVersionId.'/download')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/api/documents/'.$documentId.'/versions/'.$firstVersionId.'/download')
            ->assertHeader('Content-Disposition', 'attachment; filename=work-authorization.pdf');
        $this->actingAs($hr)->get('/api/documents/'.$documentId.'/versions/'.$firstVersionId.'/download')->assertOk();
        $this->assertDatabaseHas('employee_documents', [
            'id' => $documentId,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'title' => 'Work authorization',
            'notes' => 'Renewal in progress.',
        ]);
        $this->assertSame(
            today()->subYear()->toDateString(),
            EmployeeDocument::findOrFail($documentId)->issued_on->toDateString(),
        );
        $this->assertDatabaseHas('audit_events', [
            'event' => 'documents.version_downloaded',
            'subject_id' => $firstVersionId,
        ]);
    }

    public function test_document_upload_validates_file_metadata_and_employee_scope(): void
    {
        Storage::fake('local');
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'validation-docs-hr@test.example', 'hr');
        [, $employee] = $this->person($company, $branch, 'validation-docs-employee@test.example', 'employee');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Site',
            'code' => 'DOC-OTHER',
        ]);
        [, $otherEmployee] = $this->person($company, $otherBranch, 'validation-docs-other@test.example', 'employee');

        $this->actingAs($hr)->post('/api/documents', [
            'employee_id' => $employee->id,
            'category' => 'medical',
            'title' => 'Unsupported category',
            'issued_on' => today()->toDateString(),
            'expires_on' => today()->subDay()->toDateString(),
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'expires_on', 'file']);
        $this->post('/api/documents', [
            'employee_id' => $otherEmployee->id,
            'category' => 'certificate',
            'title' => 'Out-of-scope document',
            'file' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertNotFound();

        $this->assertDatabaseCount('employee_documents', 0);
        $this->assertDatabaseCount('employee_document_versions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('employee-documents'));
    }

    public function test_employee_documents_are_hidden_from_other_employees_and_restricted_roles(): void
    {
        Storage::fake('local');
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'scope-docs-hr@test.example', 'hr');
        [$employeeUser, $employee] = $this->person($company, $branch, 'scope-docs-owner@test.example', 'employee');
        [$otherUser] = $this->person($company, $branch, 'scope-docs-other@test.example', 'employee');
        [$manager] = $this->person($company, $branch, 'scope-docs-manager@test.example', 'manager');
        $document = EmployeeDocument::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'created_by' => $hr->id,
            'category' => 'contract',
            'title' => 'Employment agreement',
        ]);
        $version = EmployeeDocumentVersion::create([
            'employee_document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'employee-documents/'.$company->id.'/agreement.pdf',
            'original_name' => 'agreement.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 16,
            'sha256' => hash('sha256', 'private agreement'),
            'uploaded_by' => $hr->id,
        ]);
        Storage::disk('local')->put($version->file_path, 'private agreement');

        $this->actingAs($otherUser)->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(0, 'documents');
        $this->get('/api/documents/'.$document->id.'/versions/'.$version->id.'/download')->assertNotFound();
        $this->actingAs($manager)->getJson('/api/documents')->assertForbidden();
        $this->postJson('/api/documents', [])->assertForbidden();
        $this->actingAs($employeeUser)->postJson("/api/documents/{$document->id}/versions", [])->assertForbidden();
        $this->actingAs($employeeUser)->get('/api/documents/'.$document->id.'/versions/'.$version->id.'/download')->assertOk();
    }

    public function test_branch_hr_cannot_view_or_download_documents_from_another_branch(): void
    {
        Storage::fake('local');
        [$company, $branch] = $this->companyAndBranch();
        [$hr] = $this->person($company, $branch, 'branch-docs-hr@test.example', 'hr');
        $otherBranch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Other Site',
            'code' => 'DOC-OTHER-BR',
        ]);
        [$otherHr] = $this->person($company, $otherBranch, 'branch-docs-other-hr@test.example', 'hr');
        [, $otherEmployee] = $this->person($company, $otherBranch, 'branch-docs-other-employee@test.example', 'employee');
        $document = EmployeeDocument::create([
            'company_id' => $company->id,
            'branch_id' => $otherBranch->id,
            'employee_id' => $otherEmployee->id,
            'created_by' => $otherHr->id,
            'category' => 'certificate',
            'title' => 'Training certificate',
        ]);
        $version = EmployeeDocumentVersion::create([
            'employee_document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'employee-documents/'.$company->id.'/certificate.pdf',
            'original_name' => 'certificate.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'sha256' => hash('sha256', 'private'),
            'uploaded_by' => $otherHr->id,
        ]);
        Storage::disk('local')->put($version->file_path, 'private');

        $this->actingAs($hr)->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(0, 'documents')
            ->assertJsonPath('meta.total', 0);
        $this->get('/api/documents/'.$document->id.'/versions/'.$version->id.'/download')->assertNotFound();
    }

    private function companyAndBranch(): array
    {
        $company = Company::create([
            'name' => 'Documents Test Company',
            'code' => 'DOCS',
            'country' => 'AE',
            'currency' => 'AED',
        ]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Site',
            'code' => 'DOC-MAIN',
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
}
