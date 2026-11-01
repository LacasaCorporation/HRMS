<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceCycle extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'created_by', 'title', 'period_start',
        'period_end', 'due_date', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviews()
    {
        return $this->hasMany(PerformanceReview::class)->orderBy('employee_name');
    }
}
