<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelRequest extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'employee_id', 'submitted_by', 'reviewed_by', 'reconciled_by',
        'employee_code', 'employee_name', 'department', 'origin', 'destination', 'purpose',
        'departure_date', 'return_date', 'currency', 'estimated_budget', 'approved_budget',
        'itinerary_notes', 'status', 'review_note', 'reviewed_at', 'actual_total',
        'post_trip_note', 'reconciliation_note', 'report_submitted_at', 'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'estimated_budget' => 'decimal:2',
            'approved_budget' => 'decimal:2',
            'actual_total' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'report_submitted_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reconciler()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
