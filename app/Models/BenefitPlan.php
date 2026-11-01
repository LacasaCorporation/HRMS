<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenefitPlan extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'created_by', 'name', 'category', 'currency',
        'provider', 'description', 'eligibility_notes', 'status',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function enrollments()
    {
        return $this->hasMany(BenefitEnrollment::class)->orderByDesc('effective_start');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
