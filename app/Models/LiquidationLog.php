<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiquidationLog extends Model
{
    use HasFactory;

    protected $table = 'tb_liquidations_logs';

    protected $fillable = [
        'liquidation_id',
        'action',
        'performed_by',
        'remarks'
    ];

    /**
     * 🔗 Belongs to Liquidation
     */
    public function liquidation()
    {
        return $this->belongsTo(Liquidation::class, 'liquidation_id');
    }
}