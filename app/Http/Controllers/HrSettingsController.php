<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LeaveType;
use App\Support\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManager($request);

        $company = Company::query()
            ->with('branches:id,company_id,name,code,city')
            ->findOrFail($request->user()->company_id);

        return response()->json([
            'company' => $company,
            'leave_types' => LeaveType::query()
                ->where('company_id', $company->id)
                ->orderBy('name')
                ->get(['id', 'name', 'annual_entitlement', 'is_paid', 'is_active']),
            'meta' => [
                'can_manage' => true,
                'existing_balances_are_unchanged' => true,
            ],
        ]);
    }

    public function updateCompany(Request $request): JsonResponse
    {
        $this->authorizeManager($request);
        $company = Company::query()->findOrFail($request->user()->company_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2', 'uppercase'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
        ]);
        $company->update($data);
        AuditTrail::record('settings.company_updated', $company, [
            'country' => $company->country,
            'currency' => $company->currency,
        ]);

        return response()->json(['company' => $company->fresh()]);
    }

    public function storeLeaveType(Request $request): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $this->validatedLeaveType($request);
        $leaveType = LeaveType::create([
            ...$data,
            'company_id' => $request->user()->company_id,
        ]);
        AuditTrail::record('settings.leave_type_created', $leaveType);

        return response()->json(['leave_type' => $leaveType], 201);
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType): JsonResponse
    {
        $this->authorizeManager($request);
        abort_unless($leaveType->company_id === $request->user()->company_id, 404);
        $leaveType->update($this->validatedLeaveType($request, $leaveType));
        AuditTrail::record('settings.leave_type_updated', $leaveType);

        return response()->json(['leave_type' => $leaveType->fresh()]);
    }

    private function validatedLeaveType(Request $request, ?LeaveType $leaveType = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('leave_types', 'name')
                    ->where('company_id', $request->user()->company_id)
                    ->ignore($leaveType?->id),
            ],
            'annual_entitlement' => ['required', 'numeric', 'min:0', 'max:366'],
            'is_paid' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->canManagePeople(), 403, 'Only HR and administrators can manage HR settings.');
    }
}
