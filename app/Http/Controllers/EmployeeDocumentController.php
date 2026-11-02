<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentVersion;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeDocumentController extends Controller
{
    private const CATEGORIES = ['identity', 'visa', 'contract', 'qualification', 'certificate', 'other'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $query = $this->documentsFor($user);
        $total = (clone $query)->count();
        $documents = (clone $query)
            ->with([
                'employee:id,company_id,branch_id,employee_code,first_name,last_name,department,status',
                'creator:id,name',
                'versions' => fn ($versions) => $versions->with('uploader:id,name')->limit(21),
            ])
            ->orderByRaw('CASE WHEN expires_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_on')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->each(fn (EmployeeDocument $document) => $this->decorateDocument($document));

        $expiredCount = (clone $query)->whereNotNull('expires_on')->whereDate('expires_on', '<', today())->count();
        $expiringCount = (clone $query)->whereBetween('expires_on', [today(), today()->addDays(30)])->count();
        $currentCount = (clone $query)->whereDate('expires_on', '>', today()->addDays(30))->count();
        $noExpiryCount = (clone $query)->whereNull('expires_on')->count();

        return response()->json([
            'documents' => $documents,
            'employees' => $user->canManagePeople()
                ? Employee::query()
                    ->where('company_id', $user->company_id)
                    ->when($user->branch_id, fn (Builder $employees) => $employees->where('branch_id', $user->branch_id))
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'department', 'status'])
                : [],
            'categories' => self::CATEGORIES,
            'meta' => [
                'total' => $total,
                'expired' => $expiredCount,
                'expiring_soon' => $expiringCount,
                'current' => $currentCount,
                'no_expiry' => $noExpiryCount,
                'limit' => 300,
                'truncated' => $total > 300,
                'can_manage' => $user->canManagePeople(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureManager($user);
        $data = $this->validatedDocument($request);
        $employee = $this->employeeInScope($user, $data['employee_id']);

        return $this->storeDocumentWithVersion($request->file('file'), function () use ($data, $employee, $user) {
            $document = EmployeeDocument::create([
                'company_id' => $employee->company_id,
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'created_by' => $user->id,
                'category' => $data['category'],
                'title' => trim($data['title']),
                'issued_on' => $data['issued_on'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]);

            return $document;
        }, $user, true);
    }

    public function addVersion(Request $request, EmployeeDocument $document): JsonResponse
    {
        $user = $request->user();
        $this->ensureManager($user);
        $this->assertDocumentScope($user, $document);
        $data = $request->validate([
            'expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        if (! empty($data['expires_on']) && $document->issued_on?->isAfter($data['expires_on'])) {
            throw ValidationException::withMessages([
                'expires_on' => ['The expiry date cannot be before the saved issue date.'],
            ]);
        }

        return $this->storeDocumentWithVersion($request->file('file'), function () use ($data, $document, $user) {
            $lockedDocument = EmployeeDocument::query()->lockForUpdate()->findOrFail($document->id);
            $this->assertDocumentScope($user, $lockedDocument);
            if (array_key_exists('expires_on', $data)) {
                $lockedDocument->update(['expires_on' => $data['expires_on']]);
            }

            return $lockedDocument;
        }, $user, false);
    }

    public function download(Request $request, EmployeeDocument $document, EmployeeDocumentVersion $version): StreamedResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertDocumentScope($user, $document);
        abort_unless((int) $version->employee_document_id === (int) $document->id, 404);
        abort_unless(Storage::disk('local')->exists($version->file_path), 404);

        if (! $user->canManagePeople()) {
            abort_unless(
                (int) $document->employee->user_id === (int) $user->id
                && (int) $document->employee_id === (int) $user->employee?->id,
                404,
            );
        }

        AuditTrail::record('documents.version_downloaded', $version, [
            'document_id' => $document->id,
            'employee_id' => $document->employee_id,
            'version_number' => $version->version_number,
        ]);

        return Storage::disk('local')->download($version->file_path, $version->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validatedDocument(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'title' => ['required', 'string', 'max:180'],
            'issued_on' => ['nullable', 'date_format:Y-m-d'],
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issued_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        return $data;
    }

    private function storeDocumentWithVersion(
        UploadedFile $file,
        callable $resolveDocument,
        User $user,
        bool $isNew,
    ): JsonResponse {
        $path = $file->store('employee-documents/'.$user->company_id, 'local');
        if (! $path) {
            abort(500, 'The document could not be stored. Please try again.');
        }

        try {
            $document = DB::transaction(function () use ($resolveDocument, $file, $path, $user, $isNew) {
                $document = $resolveDocument();
                $lastVersion = EmployeeDocumentVersion::query()
                    ->where('employee_document_id', $document->id)
                    ->lockForUpdate()
                    ->max('version_number') ?? 0;
                $version = EmployeeDocumentVersion::create([
                    'employee_document_id' => $document->id,
                    'version_number' => $lastVersion + 1,
                    'file_path' => $path,
                    'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $user->id,
                ]);
                AuditTrail::record($isNew ? 'documents.document_uploaded' : 'documents.version_added', $document, [
                    'employee_id' => $document->employee_id,
                    'version_number' => $version->version_number,
                    'category' => $document->category,
                    'expires_on' => $document->expires_on?->toDateString(),
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        $document->refresh()->load([
            'employee:id,company_id,branch_id,employee_code,first_name,last_name,department,status',
            'creator:id,name',
            'versions' => fn ($versions) => $versions->with('uploader:id,name')->limit(21),
        ]);
        $this->decorateDocument($document);

        return response()->json(['document' => $document], $isNew ? 201 : 200);
    }

    private function documentsFor(User $user): Builder
    {
        return EmployeeDocument::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->when(! $user->canManagePeople(), fn (Builder $query) => $query->where('employee_id', $user->employee?->id ?? 0));
    }

    private function employeeInScope(User $user, int|string $employeeId): Employee
    {
        return Employee::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
            ->findOrFail($employeeId);
    }

    private function assertDocumentScope(User $user, EmployeeDocument $document): void
    {
        abort_unless((int) $document->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $document->branch_id !== (int) $user->branch_id, 404);
        if (! $user->canManagePeople()) {
            abort_unless(
                $document->employee
                && (int) $document->employee->user_id === (int) $user->id
                && (int) $document->employee_id === (int) $user->employee?->id,
                404,
            );
        }
    }

    private function decorateDocument(EmployeeDocument $document): void
    {
        $document->setAttribute('expiry_status', match (true) {
            ! $document->expires_on => 'no_expiry',
            $document->expires_on->isBefore(today()) => 'expired',
            $document->expires_on->lessThanOrEqualTo(today()->addDays(30)) => 'expiring_soon',
            default => 'valid',
        });
        $document->setAttribute('version_history_truncated', $document->versions->count() > 20);
        if ($document->version_history_truncated) {
            $document->setRelation('versions', $document->versions->take(20)->values());
        }
        $document->setAttribute('can_add_version', true);
        $document->versions->each(function (EmployeeDocumentVersion $version) {
            $version->setAttribute('download_url', route('employee-documents.download', [
                'document' => $version->employee_document_id,
                'version' => $version->id,
            ]));
        });
    }

    private function safeOriginalName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name))) ?? '';

        return mb_substr($name ?: 'document', 0, 255);
    }

    private function ensureAccess(User $user): void
    {
        abort_unless($user->canManagePeople() || $user->role === 'employee', 403, 'Employee documents are available only to HR, administrators, and employees.');
        if (! $user->canManagePeople()) {
            $this->employeeInScope($user, $user->employee?->id ?? 0);
        }
    }

    private function ensureManager(User $user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Document uploads and metadata are restricted to HR and administrators.');
    }
}
