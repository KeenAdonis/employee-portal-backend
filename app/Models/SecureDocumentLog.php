<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecureDocumentLog extends Model
{
    protected $fillable = [
        'document_id',
        'action',
        'status',
        'message',
        'email',
        'employee_name',
        'file_name',
        'user_id',
    ];

    public function document()
    {
        return $this->belongsTo(SecureDocument::class, 'document_id');
    }
}