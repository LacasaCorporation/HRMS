<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SafetyRecord;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HealthSafetyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $scope = $this->recordsFor($user);
        $records = (clone $scope)
            ->with([
                'employee:id,branch_id,employee_code,first_name,last_name,department',
                'reporter:id,name',
                'assignee:id,name',
                'resolver:id,name',
            ])
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(300)
            ->get();
        $total = (clone $scope)->count();

        return response()->json([
            'records' => $records,
            'employees' => Employee::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'department']),
            'assignees' => $this->assigneesFor($user)->orderBy('name')->get(['id', 'name', 'role']),
            'meta' => [
                'total' => $total,
                'open' => (clone $scope)->where('status', 'open')->count(),
                'in_progress' => (clone $scope)->where('status', 'in_progress')->count(),
                'resolved' => (clone $scope)->where('status', 'resolved')->count(),
                'limit' => 300,
                'truncated' => $total > 300,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $data = $request->validate([
            'record_type' => ['required', Rule::in(['incident', 'hazard', 'inspection', 'ppe'])],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:180'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'summary' => ['required', 'string', 'max:4000'],
            'employee_id' => ['nullable', 'integer'],
        ]);

        $record = DB::transaction(function () use ($data, $user) {
            $employee = null;
            if (! empty($data['employee_id'])) {
                $employee = Employee::query()
                    ->where('company_id', $user->company_id)
                    ->where('status', 'active')
                    ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                    ->lockForUpdate()
                    ->findOrFail($data['employee_id']);
            }

            $record = SafetyRecord::create([
                'company_id' => $user->company_id,
                'branch_id' => $employee?->branch_id ?? $user->branch_id,
                'employee_id' => $employee?->id,
                'reported_by' => $user->id,
                'record_type' => $data['record_type'],
                'occurred_on' => $data['occurred_on'],
                'location' => trim($data['location'] ?? '') ?: null,
                'priority' => $data['priority'],
                'summary' => trim($data['summary']),
                'status' => 'open',
            ]);
            AuditTrail::record('health_safety.record_opened', $record, [
                'record_type' => $record->record_type,
                'priority' => $record->priority,
                'employee_id' => $record->employee_id,
            ]);

            return $record;
        });

        return response()->json(['record' => $this->loadRecord($record)], 201);
    }

    public function assign(Request $request, SafetyRecord $record): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertRecordScope($user, $record);
        $data = $request->validate([
            'assigned_to' => ['required', 'integer'],
            'action_plan' => ['required', 'string', 'max:4000'],
        ]);

        $record = DB::transaction(function () use ($data, $record, $user) {
            $lockedRecord = SafetyRecord::query()->lockForUpdate()->findOrFail($record->id);
            $this->assertRecordScope($user, $lockedRecord);
            abort_unless($lockedRecord->status === 'open', 409, 'Only open safety records can be assigned.');
            $assignee = $this->assigneesFor($user)->findOrFail($data['assigned_to']);
            $lockedRecord->update([
                'status' => 'in_progress',
                'assigned_to' => $assignee->id,
                'action_plan' => trim($data['action_plan']),
            ]);
            AuditTrail::record('health_safety.follow_up_assigned', $lockedRecord, [
                'assigned_to' => $assignee->id,
            ]);

            return $lockedRecord;
        });

        return response()->json(['record' => $this->loadRecord($record)]);
    }

    public function resolve(Request $request, SafetyRecord $record): JsonResponse
    {
        $user = $request->user();
        $this->ensureAccess($user);
        $this->assertRecordScope($user, $record);
        $data = $request->validate([
            'resolution_summary' => ['required', 'string', 'max:4000'],
        ]);

        $record = DB::transaction(function () use ($data, $record, $user) {
            $lockedRecord = SafetyRecord::query()->lockForUpdate()->findOrFail($record->id);
            $this->assertRecordScope($user, $lockedRecord);
            abort_unless($lockedRecord->status === 'in_progress', 409, 'Assign follow-up before resolving a safety record.');
            $lockedRecord->update([
                'status' => 'resolved',
                'resolution_summary' => trim($data['resolution_summary']),
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);
            AuditTrail::record('health_safety.record_resolved', $lockedRecord, [
                'assigned_to' => $lockedRecord->assigned_to,
            ]);

            return $lockedRecord;
        });

        return response()->json(['record' => $this->loadRecord($record)]);
    }

    private function loadRecord(SafetyRecord $record): SafetyRecord
    {
        return $record->fresh()->load([
            'employee:id,branch_id,employee_code,first_name,last_name,department',
            'reporter:id,name',
            'assignee:id,name',
            'resolver:id,name',
        ]);
    }

    private function recordsFor(User $user): Builder
    {
        return SafetyRecord::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function assigneesFor(User $user): Builder
    {
        return User::query()
            ->where('company_id', $user->company_id)
            ->whereIn('role', ['hr', 'admin'])
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function assertRecordScope(User $user, SafetyRecord $record): void
    {
        abort_unless((int) $record->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $record->branch_id !== (int) $user->branch_id, 404);
    }

    private function ensureAccess(User $user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Health and safety records are restricted to HR and administrators.');
    }
}
