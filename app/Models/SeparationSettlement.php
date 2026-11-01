<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeparationSettlement extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'lifecycle_case_id', 'employee_id',
        'created_by', 'submitted_by', 'reviewed_by', 'employee_code',
        'employee_name', 'department', 'currency', 'status', 'notes',
        'review_note', 'submitted_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function offboardingCase()
    {
        return $this->belongsTo(LifecycleCase::class, 'lifecycle_case_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(SeparationSettlementItem::class)->orderBy('id');
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
