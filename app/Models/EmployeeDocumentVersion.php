<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocumentVersion extends Model
{
    protected $hidden = [
        'file_path',
        'sha256',
    ];

    protected $fillable = [
        'employee_document_id',
        'version_number',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'sha256',
        'uploaded_by',
    ];

    public function document()
    {
        return $this->belongsTo(EmployeeDocument::class, 'employee_document_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
