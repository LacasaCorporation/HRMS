<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingProgram extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'created_by', 'title', 'category',
        'provider', 'delivery_mode', 'start_date', 'due_date', 'capacity',
        'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'capacity' => 'integer',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function enrollments()
    {
        return $this->hasMany(TrainingEnrollment::class)->orderBy('employee_name');
    }
}
