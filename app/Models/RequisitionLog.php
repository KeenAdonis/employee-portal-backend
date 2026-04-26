<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitionLog extends Model
{
    protected $table = 'tb_requisition_logs';

    protected $fillable = [
        'RequestId',
        'Action',
        'PerformedBy',
        'PerformedAt',
        'Remarks'
    ];

    protected $casts = [
        'PerformedAt' => 'datetime',
    ];

    public function requisition()
    {
        return $this->belongsTo(
            Requisition::class,
            'RequestId',
            'RequestId'
        );
    }
}