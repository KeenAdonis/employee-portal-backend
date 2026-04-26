<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitionParticular extends Model
{
    protected $table = 'tb_requisition_particular';

    protected $fillable = [
        'RequestId',
        'ParticularId',
        'Particulars',
        'Amount'
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