<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'department_id', 'reports_to_position_id',
        'title', 'code', 'grade', 'employment_type', 'headcount', 'status',
    ];

    protected function casts(): array
    {
        return ['headcount' => 'integer'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function reportsTo()
    {
        return $this->belongsTo(self::class, 'reports_to_position_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
