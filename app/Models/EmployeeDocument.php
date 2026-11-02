<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'category',
        'title',
        'issued_on',
        'expires_on',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
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

    public function versions()
    {
        return $this->hasMany(EmployeeDocumentVersion::class)->orderByDesc('version_number');
    }
}
