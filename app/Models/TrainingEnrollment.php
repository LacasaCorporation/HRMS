<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingEnrollment extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'training_program_id', 'employee_id',
        'assigned_by', 'employee_code', 'employee_name', 'department',
        'status', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function program()
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
