<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelLog extends Model
{
    protected $table = 'tb_travel_logs';

    protected $fillable = [
        'travel_request_id',
        'travel_liquidation_id',
        'action',
        'description',
        'performed_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | TRAVEL REQUEST
    |--------------------------------------------------------------------------
    */
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(
            TravelRequest::class,
            'travel_request_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRAVEL LIQUIDATION
    |--------------------------------------------------------------------------
    */
    public function liquidation(): BelongsTo
    {
        return $this->belongsTo(
            TravelLiquidation::class,
            'travel_liquidation_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PERFORMED BY
    |--------------------------------------------------------------------------
    */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'performed_by'
        );
    }
}