<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelLiquidation extends Model
{
    protected $table = 'tb_travel_liquidations';

    protected $fillable = [
        'travel_request_id',
        'total_mileage',
        'fuel_cost',
        'toll_fee',
        'parking_fee',
        'other_expenses',
        'total_cost',
        'remarks',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | TRAVEL REQUEST
    |--------------------------------------------------------------------------
    */
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVER
    |--------------------------------------------------------------------------
    */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | STOPS
    |--------------------------------------------------------------------------
    */
    public function stops(): HasMany
    {
        return $this->hasMany(
            TravelLiquidationStop::class,
            'travel_liquidation_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ATTACHMENTS
    |--------------------------------------------------------------------------
    */
    public function attachments(): HasMany
    {
        return $this->hasMany(
            TravelAttachment::class,
            'travel_liquidation_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOGS
    |--------------------------------------------------------------------------
    */
    public function logs(): HasMany
    {
        return $this->hasMany(
            TravelLog::class,
            'travel_liquidation_id'
        );
    }
}