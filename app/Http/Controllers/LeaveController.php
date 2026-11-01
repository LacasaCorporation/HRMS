<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\AuditTrail;
use App\Support\PeopleScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employeeIds = PeopleScope::employees($request->user())->select('id');
        $requests = LeaveRequest::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('employee_id', $employeeIds)
            ->with(['employee:id,first_name,last_name,employee_code,manager_id', 'leaveType:id,name'])
            ->latest()
            ->limit(100)
            ->get();

        $balances = LeaveBalance::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('year', now()->year)
            ->with(['leaveType:id,name'])
            ->get();

        return response()->json([
            'requests' => $requests,
            'balances' => $balances,
            'types' => LeaveType::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'annual_entitlement', 'is_paid']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $self = Employee::query()->where('user_id', $user->id)->first();
        $data = $request->validate([
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('company_id', $user->company_id)],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('company_id', $user->company_id)],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $employee = $user->canManagePeople()
            ? Employee::query()->where('company_id', $user->company_id)->findOrFail($data['employee_id'] ?? $self?->id)
            : $self;
        abort_unless($employee, 403, 'Your sign-in is not linked to an employee record.');
        abort_unless(PeopleScope::employees($user)->whereKey($employee->id)->exists(), 403);

        $days = $this->weekdays($data['start_date'], $data['end_date']);
        abort_if($days < 1, 422, 'The selected range must contain at least one working day.');
        abort_if(
            Carbon::parse($data['start_date'])->year !== Carbon::parse($data['end_date'])->year,
            422,
            'A leave request must stay within one calendar year in this foundation.',
        );

        $leaveRequest = LeaveRequest::create([
            'company_id' => $user->company_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'requested_days' => $days,
            'reason' => $data['reason'],
        ]);
        AuditTrail::record('leave.requested', $leaveRequest, ['days' => $days]);

        return response()->json(['request' => $leaveRequest->load(['employee:id,first_name,last_name,employee_code', 'leaveType:id,name'])], 201);
    }

    public function review(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $user = $request->user();
        abort_unless($leaveRequest->company_id === $user->company_id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($user, $leaveRequest, $data) {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($leaveRequest->id);
            abort_unless(PeopleScope::canReview($user, $locked->employee), 403);
            abort_unless($locked->status === 'pending', 409, 'This request has already been reviewed.');

            if ($data['decision'] === 'approved') {
                $balance = LeaveBalance::query()
                    ->where('employee_id', $locked->employee_id)
                    ->where('leave_type_id', $locked->leave_type_id)
                    ->where('year', Carbon::parse($locked->start_date)->year)
                    ->lockForUpdate()
                    ->first();

                abort_unless($balance, 422, 'No leave balance is configured for the selected year and type.');
                abort_if((float) $balance->entitled_days - (float) $balance->used_days < (float) $locked->requested_days, 422, 'The employee does not have enough available leave balance.');
                $balance->increment('used_days', $locked->requested_days);
            }

            $locked->update([
                'status' => $data['decision'],
                'reviewed_by' => $user->id,
                'review_note' => $data['review_note'] ?? null,
                'reviewed_at' => now(),
            ]);

            AuditTrail::record('leave.'.$data['decision'], $locked, ['review_note' => $data['review_note'] ?? null]);

            return response()->json(['request' => $locked->fresh()->load(['employee:id,first_name,last_name,employee_code', 'leaveType:id,name'])]);
        });
    }

    private function weekdays(string $start, string $end): int
    {
        $count = 0;
        for ($date = Carbon::parse($start); $date->lte(Carbon::parse($end)); $date->addDay()) {
            if (! $date->isWeekend()) {
                $count++;
            }
        }

        return $count;
    }
}
