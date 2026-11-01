<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanAdvance extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'employee_id', 'submitted_by', 'reviewed_by',
        'employee_code', 'employee_name', 'department', 'currency', 'requested_amount',
        'requested_installments', 'purpose', 'status', 'approved_amount',
        'repayment_installments', 'first_due_date', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'first_due_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function installments()
    {
        return $this->hasMany(LoanAdvanceInstallment::class)->orderBy('sequence');
    }
}
