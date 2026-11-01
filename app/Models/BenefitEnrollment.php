<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenefitEnrollment extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'benefit_plan_id', 'employee_id',
        'created_by', 'submitted_by', 'reviewed_by', 'employee_code',
        'employee_name', 'department', 'effective_start', 'effective_end',
        'employee_contribution', 'employer_contribution', 'notes',
        'status', 'review_note', 'submitted_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_start' => 'date',
            'effective_end' => 'date',
            'employee_contribution' => 'decimal:2',
            'employer_contribution' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(BenefitPlan::class, 'benefit_plan_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
