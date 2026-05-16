<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'float',
        'meta' => 'array'
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}