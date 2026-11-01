<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SafetyRecord extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'employee_id', 'reported_by', 'assigned_to', 'resolved_by',
        'record_type', 'occurred_on', 'location', 'priority', 'summary', 'action_plan',
        'status', 'resolution_summary', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
