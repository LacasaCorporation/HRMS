<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['company_id', 'name', 'annual_entitlement', 'is_paid', 'is_active'];

    protected function casts(): array
    {
        return ['annual_entitlement' => 'decimal:2', 'is_paid' => 'boolean', 'is_active' => 'boolean'];
    }
}
