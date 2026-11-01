<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanAdvanceInstallment extends Model
{
    protected $fillable = ['loan_advance_id', 'sequence', 'due_date', 'amount'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function loanAdvance()
    {
        return $this->belongsTo(LoanAdvance::class);
    }

    public function repayments()
    {
        return $this->hasMany(LoanAdvanceRepayment::class)->orderBy('recorded_date')->orderBy('id');
    }
}
