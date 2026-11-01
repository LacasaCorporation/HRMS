<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'entitled_days', 'used_days'];

    protected function casts(): array
    {
        return ['entitled_days' => 'decimal:2', 'used_days' => 'decimal:2'];
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
