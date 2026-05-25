<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Requisition;

class Liquidation extends Model
{
    use HasFactory;

    protected $table = 'tb_liquidations';

    protected $fillable = [
        'request_id',
        'cash_advance',
        'total_expenses',
        'amount_reimbursement',
        'amount_returned',
        'status',
        'remarks'
    ];

    protected $casts = [
        'cash_advance' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'amount_reimbursement' => 'decimal:2',
        'amount_returned' => 'decimal:2',
    ];

    /* =========================
       🔗 RELATIONSHIPS
    ========================= */

    public function particulars()
    {
        return $this->hasMany(LiquidationParticular::class, 'liquidation_id');
    }

    public function logs()
    {
        return $this->hasMany(LiquidationLog::class, 'liquidation_id');
    }

    // ✅ IMPORTANT (NEW)
    public function requisition()
    {
        return $this->belongsTo(
            Requisition::class,
            'request_id',
            'RequestId'
        );
    }
}