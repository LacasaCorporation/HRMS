<?php

namespace App\Http\Controllers;

use App\Models\AssetAssignment;
use App\Models\CompanyAsset;
use App\Models\Employee;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssetsUniformsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->canManagePeople()) {
            $scope = $this->assetsFor($user);
            $assignments = $this->assignmentsFor($user)
                ->with($this->assignmentRelations())
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->limit(300)
                ->get();
            $total = (clone $scope)->count();

            return response()->json([
                'view' => 'inventory',
                'assets' => (clone $scope)
                    ->with(['currentAssignment.employee:id,employee_code,first_name,last_name'])
                    ->orderByRaw("CASE status WHEN 'available' THEN 0 WHEN 'pending_acknowledgement' THEN 1 WHEN 'assigned' THEN 2 ELSE 3 END")
                    ->orderBy('name')
                    ->limit(300)
                    ->get(),
                'assignments' => $assignments,
                'employees' => Employee::query()
                    ->where('company_id', $user->company_id)
                    ->where('status', 'active')
                    ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get(['id', 'branch_id', 'employee_code', 'first_name', 'last_name', 'department']),
                'meta' => [
                    'total' => $total,
                    'available' => (clone $scope)->where('status', 'available')->count(),
                    'pending_acknowledgement' => (clone $scope)->where('status', 'pending_acknowledgement')->count(),
                    'assigned' => (clone $scope)->where('status', 'assigned')->count(),
                    'maintenance' => (clone $scope)->where('status', 'maintenance')->count(),
                    'lost' => (clone $scope)->where('status', 'lost')->count(),
                    'limit' => 300,
                    'truncated' => $total > 300,
                    'can_manage' => true,
                ],
            ]);
        }

        $employee = $this->employeeFor($user);
        $assignments = AssetAssignment::query()
            ->where('company_id', $user->company_id)
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending_acknowledgement', 'assigned'])
            ->with($this->assignmentRelations())
            ->orderByRaw("CASE status WHEN 'pending_acknowledgement' THEN 0 ELSE 1 END")
            ->orderByDesc('assigned_at')
            ->limit(100)
            ->get();

        return response()->json([
            'view' => 'my_assets',
            'assets' => [],
            'assignments' => $assignments,
            'employees' => [],
            'meta' => [
                'total' => $assignments->count(),
                'pending_acknowledgement' => $assignments->where('status', 'pending_acknowledgement')->count(),
                'assigned' => $assignments->where('status', 'assigned')->count(),
                'can_manage' => false,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureManager($user);
        if (is_string($request->input('asset_tag'))) {
            $request->merge(['asset_tag' => trim($request->input('asset_tag'))]);
        }
        $data = $request->validate([
            'asset_tag' => [
                'required', 'string', 'max:80',
                Rule::unique('company_assets', 'asset_tag')->where('company_id', $user->company_id),
            ],
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(['equipment', 'uniform', 'ppe', 'other'])],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'condition' => ['required', Rule::in(['new', 'good', 'fair'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $asset = DB::transaction(function () use ($data, $user) {
            $asset = CompanyAsset::create([
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'asset_tag' => $data['asset_tag'],
                'name' => trim($data['name']),
                'category' => $data['category'],
                'serial_number' => trim($data['serial_number'] ?? '') ?: null,
                'condition' => $data['condition'],
                'status' => 'available',
                'notes' => trim($data['notes'] ?? '') ?: null,
                'created_by' => $user->id,
            ]);
            AuditTrail::record('assets_uniforms.asset_registered', $asset, [
                'asset_tag' => $asset->asset_tag,
                'category' => $asset->category,
            ]);

            return $asset;
        });

        return response()->json(['asset' => $asset->fresh()], 201);
    }

    public function assign(Request $request, CompanyAsset $asset): JsonResponse
    {
        $user = $request->user();
        $this->ensureManager($user);
        $this->assertAssetScope($user, $asset);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
        ]);

        $assignment = DB::transaction(function () use ($data, $asset, $user) {
            $lockedAsset = CompanyAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $this->assertAssetScope($user, $lockedAsset);
            abort_unless($lockedAsset->status === 'available', 409, 'Only available assets can be assigned.');
            $employee = Employee::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->when($lockedAsset->branch_id, fn (Builder $query) => $query->where('branch_id', $lockedAsset->branch_id))
                ->findOrFail($data['employee_id']);
            $assignmentBranchId = $lockedAsset->branch_id ?? $employee->branch_id;

            $assignment = AssetAssignment::create([
                'company_id' => $lockedAsset->company_id,
                'branch_id' => $assignmentBranchId,
                'asset_id' => $lockedAsset->id,
                'employee_id' => $employee->id,
                'assigned_by' => $user->id,
                'status' => 'pending_acknowledgement',
                'condition_at_issue' => $lockedAsset->condition,
                'assigned_at' => now(),
            ]);
            $lockedAsset->update([
                'branch_id' => $assignmentBranchId,
                'status' => 'pending_acknowledgement',
            ]);
            AuditTrail::record('assets_uniforms.asset_handed_over', $assignment, [
                'asset_id' => $lockedAsset->id,
                'employee_id' => $employee->id,
            ]);

            return $assignment;
        });

        return response()->json(['assignment' => $assignment->fresh()->load($this->assignmentRelations())], 201);
    }

    public function acknowledge(Request $request, AssetAssignment $assignment): JsonResponse
    {
        $user = $request->user();
        $employee = $this->employeeFor($user);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'decline'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('decision') === 'decline')],
        ]);
        if ($data['decision'] === 'decline' && ! trim($data['note'] ?? '')) {
            throw ValidationException::withMessages([
                'note' => ['Add a short reason when declining a handover.'],
            ]);
        }

        $assignment = DB::transaction(function () use ($data, $assignment, $employee, $user) {
            $lockedAssignment = AssetAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless(
                (int) $lockedAssignment->company_id === (int) $user->company_id
                && (int) $lockedAssignment->employee_id === (int) $employee->id,
                404,
            );
            abort_unless($lockedAssignment->status === 'pending_acknowledgement', 409, 'This handover is no longer awaiting your response.');
            $asset = CompanyAsset::query()->lockForUpdate()->findOrFail($lockedAssignment->asset_id);

            if ($data['decision'] === 'accept') {
                $lockedAssignment->update([
                    'status' => 'assigned',
                    'acknowledged_by' => $user->id,
                    'acknowledged_at' => now(),
                    'acknowledgement_note' => trim($data['note'] ?? '') ?: null,
                ]);
                $asset->update(['status' => 'assigned']);
                AuditTrail::record('assets_uniforms.handover_acknowledged', $lockedAssignment, [
                    'asset_id' => $asset->id,
                ]);
            } else {
                $lockedAssignment->update([
                    'status' => 'declined',
                    'declined_by' => $user->id,
                    'declined_at' => now(),
                    'acknowledgement_note' => trim($data['note']),
                ]);
                $asset->update(['status' => 'available']);
                AuditTrail::record('assets_uniforms.handover_declined', $lockedAssignment, [
                    'asset_id' => $asset->id,
                ]);
            }

            return $lockedAssignment;
        });

        return response()->json(['assignment' => $assignment->fresh()->load($this->assignmentRelations())]);
    }

    public function recordReturn(Request $request, AssetAssignment $assignment): JsonResponse
    {
        $user = $request->user();
        $this->ensureManager($user);
        $this->assertAssignmentScope($user, $assignment);
        $data = $request->validate([
            'return_condition' => ['required', Rule::in(['new', 'good', 'fair', 'damaged', 'lost'])],
            'return_notes' => [
                'nullable', 'string', 'max:2000',
                Rule::requiredIf(fn () => in_array($request->input('return_condition'), ['damaged', 'lost'], true)),
            ],
        ]);
        if (in_array($data['return_condition'], ['damaged', 'lost'], true) && ! trim($data['return_notes'] ?? '')) {
            throw ValidationException::withMessages([
                'return_notes' => ['Describe the damage or loss before recording this return.'],
            ]);
        }

        $assignment = DB::transaction(function () use ($data, $assignment, $user) {
            $lockedAssignment = AssetAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->assertAssignmentScope($user, $lockedAssignment);
            abort_unless($lockedAssignment->status === 'assigned', 409, 'Only acknowledged assignments can be returned.');
            $asset = CompanyAsset::query()->lockForUpdate()->findOrFail($lockedAssignment->asset_id);
            $condition = $data['return_condition'];
            $assetStatus = match ($condition) {
                'damaged' => 'maintenance',
                'lost' => 'lost',
                default => 'available',
            };
            $lockedAssignment->update([
                'status' => 'returned',
                'returned_by' => $user->id,
                'return_condition' => $condition,
                'return_notes' => trim($data['return_notes'] ?? '') ?: null,
                'returned_at' => now(),
            ]);
            $asset->update([
                'condition' => $condition,
                'status' => $assetStatus,
            ]);
            AuditTrail::record('assets_uniforms.asset_return_recorded', $lockedAssignment, [
                'asset_id' => $asset->id,
                'return_condition' => $condition,
                'asset_status' => $assetStatus,
            ]);

            return $lockedAssignment;
        });

        return response()->json(['assignment' => $assignment->fresh()->load($this->assignmentRelations())]);
    }

    public function completeMaintenance(Request $request, CompanyAsset $asset): JsonResponse
    {
        $user = $request->user();
        $this->ensureManager($user);
        $this->assertAssetScope($user, $asset);
        $data = $request->validate([
            'condition' => ['required', Rule::in(['new', 'good', 'fair'])],
            'maintenance_note' => ['required', 'string', 'max:2000'],
        ]);

        $asset = DB::transaction(function () use ($data, $asset, $user) {
            $lockedAsset = CompanyAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $this->assertAssetScope($user, $lockedAsset);
            abort_unless($lockedAsset->status === 'maintenance', 409, 'Only assets in maintenance can be released.');
            $lockedAsset->update([
                'condition' => $data['condition'],
                'status' => 'available',
                'maintenance_note' => trim($data['maintenance_note']),
            ]);
            AuditTrail::record('assets_uniforms.maintenance_completed', $lockedAsset, [
                'condition' => $lockedAsset->condition,
            ]);

            return $lockedAsset;
        });

        return response()->json(['asset' => $asset->fresh()]);
    }

    private function assetsFor(User $user): Builder
    {
        return CompanyAsset::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function assignmentsFor(User $user): Builder
    {
        return AssetAssignment::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function employeeFor(User $user): Employee
    {
        abort_unless($user->role === 'employee', 403, 'Asset handovers are available only to HR, administrators, and employees.');

        return Employee::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function assertAssetScope(User $user, CompanyAsset $asset): void
    {
        abort_unless((int) $asset->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $asset->branch_id !== (int) $user->branch_id, 404);
    }

    private function assertAssignmentScope(User $user, AssetAssignment $assignment): void
    {
        abort_unless((int) $assignment->company_id === (int) $user->company_id, 404);
        abort_if($user->branch_id && (int) $assignment->branch_id !== (int) $user->branch_id, 404);
    }

    private function assignmentRelations(): array
    {
        return [
            'asset:id,asset_tag,name,category,serial_number,condition,status,branch_id,maintenance_note',
            'employee:id,branch_id,employee_code,first_name,last_name,department',
            'assigner:id,name',
            'acknowledger:id,name',
            'decliner:id,name',
            'returner:id,name',
        ];
    }

    private function ensureManager(User $user): void
    {
        abort_unless($user->canManagePeople(), 403, 'Asset inventory changes are restricted to HR and administrators.');
    }
}
