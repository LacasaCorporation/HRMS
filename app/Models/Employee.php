<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'department_id', 'position_id', 'user_id', 'manager_id', 'employee_code',
        'first_name', 'last_name', 'work_email', 'phone', 'job_title',
        'department', 'employment_type', 'status', 'join_date',
        'monthly_salary', 'currency',
    ];

    protected function casts(): array
    {
        return ['join_date' => 'date', 'monthly_salary' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function departmentRecord()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
