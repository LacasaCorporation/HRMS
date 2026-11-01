<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceEntry extends Model
{
    protected $fillable = [
        'company_id', 'employee_id', 'attendance_date', 'check_in', 'check_out',
        'status', 'note', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
