<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PeopleScope
{
    public static function employees(User $user): Builder
    {
        $query = Employee::query()
            ->where('company_id', $user->company_id)
            ->when($user->branch_id, fn (Builder $builder) => $builder->where('branch_id', $user->branch_id));

        if ($user->canManagePeople()) {
            return $query;
        }

        $ownEmployeeId = Employee::query()->where('user_id', $user->id)->value('id');

        if ($user->role === 'manager' && $ownEmployeeId) {
            return $query->where(fn (Builder $builder) => $builder
                ->where('id', $ownEmployeeId)
                ->orWhere('manager_id', $ownEmployeeId));
        }

        return $query->where('user_id', $user->id);
    }

    public static function canReview(User $reviewer, Employee $employee): bool
    {
        if ($reviewer->company_id !== $employee->company_id) {
            return false;
        }

        if (! self::employees($reviewer)->whereKey($employee->id)->exists()) {
            return false;
        }

        if ($reviewer->canManagePeople()) {
            return true;
        }

        return $reviewer->role === 'manager'
            && $employee->manager_id === Employee::query()->where('user_id', $reviewer->id)->value('id');
    }
}
