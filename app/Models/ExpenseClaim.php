<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseClaim extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'employee_id', 'submitted_by', 'reviewed_by',
        'employee_code', 'employee_name', 'department', 'category', 'merchant',
        'expense_date', 'amount', 'currency', 'description', 'receipt_path',
        'receipt_original_name', 'receipt_mime', 'receipt_size', 'status',
        'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
