<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollCycle extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'branch_scope_key', 'created_by', 'submitted_by', 'reviewed_by',
        'period_start', 'period_end', 'pay_date', 'currency', 'status',
        'review_note', 'submitted_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'pay_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class)->orderBy('employee_name');
    }

    public function createdBy()
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
