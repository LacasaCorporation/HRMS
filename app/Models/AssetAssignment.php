<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'asset_id',
        'employee_id',
        'assigned_by',
        'acknowledged_by',
        'declined_by',
        'returned_by',
        'status',
        'condition_at_issue',
        'acknowledgement_note',
        'return_condition',
        'return_notes',
        'assigned_at',
        'acknowledged_at',
        'declined_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'declined_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(CompanyAsset::class, 'asset_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function acknowledger()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function decliner()
    {
        return $this->belongsTo(User::class, 'declined_by');
    }

    public function returner()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
