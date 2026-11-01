<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifecycleCase extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'employee_id', 'created_by', 'type',
        'status', 'effective_date', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function tasks()
    {
        return $this->hasMany(LifecycleTask::class)->orderBy('due_date')->orderBy('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
