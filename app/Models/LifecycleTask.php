<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifecycleTask extends Model
{
    protected $fillable = [
        'lifecycle_case_id', 'title', 'owner_team', 'due_date',
        'status', 'notes', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function lifecycleCase()
    {
        return $this->belongsTo(LifecycleCase::class);
    }
}
