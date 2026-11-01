<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidate extends Model
{
    protected $fillable = [
        'company_id', 'requisition_id', 'created_by', 'hired_employee_id',
        'first_name', 'last_name', 'email', 'phone', 'source', 'stage',
        'rating', 'notes', 'applied_at', 'hired_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'applied_at' => 'datetime',
            'hired_at' => 'datetime',
        ];
    }

    public function requisition()
    {
        return $this->belongsTo(RecruitmentRequisition::class, 'requisition_id');
    }

    public function hiredEmployee()
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
