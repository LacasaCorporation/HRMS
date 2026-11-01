<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftPattern extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'name', 'code', 'start_time', 'end_time',
        'break_minutes', 'status',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments()
    {
        return $this->hasMany(RosterAssignment::class);
    }
}
