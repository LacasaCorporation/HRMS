<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentRequisition extends Model
{
    protected $table = 'recruitment_requisitions';

    protected $fillable = [
        'company_id', 'branch_id', 'department_id', 'position_id', 'created_by',
        'code', 'title', 'employment_type', 'work_mode', 'location', 'openings',
        'priority', 'status', 'target_date', 'description',
    ];

    protected function casts(): array
    {
        return [
            'openings' => 'integer',
            'target_date' => 'date',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function candidates()
    {
        return $this->hasMany(RecruitmentCandidate::class, 'requisition_id');
    }
}
