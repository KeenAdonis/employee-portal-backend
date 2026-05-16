<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelLiquidationStop extends Model
{
    protected $table = 'tb_travel_liquidation_stops';

    protected $fillable = [
        'travel_liquidation_id',
        'from_location',
        'to_location',
        'odometer_start',
        'odometer_end',
        'mileage',
    ];

    protected $casts = [
        'odometer_start' => 'decimal:2',
        'odometer_end' => 'decimal:2',
        'mileage' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | LIQUIDATION
    |--------------------------------------------------------------------------
    */
    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(
            TravelLiquidation::class,
            'travel_liquidation_id'
        );
    }
}