<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_cycle_id', 'employee_id', 'employee_code', 'employee_name', 'department',
        'base_pay', 'other_earnings', 'manual_deductions', 'gross_pay', 'net_pay',
        'notes', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'base_pay' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'manual_deductions' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function cycle()
    {
        return $this->belongsTo(PayrollCycle::class, 'payroll_cycle_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
