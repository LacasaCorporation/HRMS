<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RosterAssignment extends Model
{
    protected $fillable = [
        'company_id', 'employee_id', 'shift_pattern_id', 'shift_date', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['shift_date' => 'date'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftPattern()
    {
        return $this->belongsTo(ShiftPattern::class);
    }
}
