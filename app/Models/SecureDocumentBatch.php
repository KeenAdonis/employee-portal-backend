<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecureDocumentBatch extends Model
{
    protected $fillable = [
        'employee_name',
        'password_encrypted',
        'created_by',
    ];

    public function documents()
    {
        return $this->hasMany(SecureDocument::class, 'batch_id');
    }
}
