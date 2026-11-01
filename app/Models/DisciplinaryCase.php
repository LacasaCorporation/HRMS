<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryCase extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'employee_id', 'created_by', 'investigator_id', 'resolved_by',
        'case_type', 'incident_date', 'summary', 'status', 'investigation_notes',
        'employee_response', 'response_recorded_at', 'resolution_summary', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'response_recorded_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function investigator()
    {
        return $this->belongsTo(User::class, 'investigator_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
