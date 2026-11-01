<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'performance_cycle_id', 'employee_id',
        'created_by', 'submitted_by', 'reviewed_by', 'employee_code',
        'employee_name', 'department', 'rating', 'goals', 'results',
        'strengths', 'development_focus', 'status', 'review_note',
        'submitted_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function cycle()
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
