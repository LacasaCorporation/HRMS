<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyAsset extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'asset_tag',
        'name',
        'category',
        'serial_number',
        'condition',
        'status',
        'notes',
        'maintenance_note',
        'created_by',
    ];

    public function employeeAssignments()
    {
        return $this->hasMany(AssetAssignment::class, 'asset_id');
    }

    public function currentAssignment()
    {
        return $this->hasOne(AssetAssignment::class, 'asset_id')
            ->whereIn('status', ['pending_acknowledgement', 'assigned']);
    }
}
