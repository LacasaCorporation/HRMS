<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanAdvanceRepayment extends Model
{
    protected $fillable = ['loan_advance_installment_id', 'recorded_by', 'amount', 'recorded_date', 'reference'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'recorded_date' => 'date'];
    }

    public function installment()
    {
        return $this->belongsTo(LoanAdvanceInstallment::class, 'loan_advance_installment_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
