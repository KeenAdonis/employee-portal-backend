<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class RequisitionAttachment extends Model
{
    protected $table = 'tb_requisition_attachments';

    protected $fillable = [
        'RequestId',
        'FileName',
        'FilePath',
        'FileType',
        'FileSize',
    ];

    /* =========================
       RELATIONSHIP
    ========================= */
    public function requisition()
    {
        return $this->belongsTo(
            Requisition::class,
            'RequestId',
            'RequestId'
        );
    }

    /* =========================
       ACCESSOR
    ========================= */
    protected $appends = ['file_url'];

    

    public function getFileUrlAttribute()
    {
        return url(Storage::url($this->FilePath));
    }
}