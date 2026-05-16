<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiquidationParticular extends Model
{
    use HasFactory;

    protected $table = 'tb_liquidations_particulars';

    protected $fillable = [
        'liquidation_id',
        'particulars',
        'or_no',
        'amount'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * 🔗 Belongs to Liquidation
     */
    public function liquidation()
    {
        return $this->belongsTo(Liquidation::class, 'liquidation_id');
    }
}