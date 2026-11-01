<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PeopleScope::employees($request->user())
            ->with(['branch:id,name', 'manager:id,first_name,last_name'])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($builder) => $builder
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->canManagePeople(), 403, 'Only HR and administrators can add employees.');

        $rules = [
            'employee_code' => ['required', 'string', 'max:32', Rule::unique('employees')->where('company_id', $user->company_id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'work_email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['required', 'string', 'max:150'],
            'department' => ['required', 'string', 'max:100'],
            'employment_type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Temporary'])],
            'join_date' => ['required', 'date'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $user->company_id)],
        ];
        if ($user->branch_id) {
            $rules['branch_id'][] = Rule::in([$user->branch_id]);
        }
        $data = $request->validate($rules);

        $employee = Employee::create($data + [
            'company_id' => $user->company_id,
            'branch_id' => $data['branch_id'] ?? $user->branch_id,
            'status' => 'active',
            'currency' => $user->company?->currency ?? 'AED',
        ]);

        AuditTrail::record('employee.created', $employee, ['employee_code' => $employee->employee_code]);

        return response()->json(['employee' => $employee->load('branch:id,name')], 201);
    }
}
