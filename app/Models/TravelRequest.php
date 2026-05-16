<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Models\Employee;
use App\Models\TravelDestination;
use App\Models\TravelLiquidation;
use App\Models\TravelAttachment;
use App\Models\TravelLog;

class TravelRequest extends Model
{
    protected $table = 'tb_travel_requests';

    protected $fillable = [
        'travel_no',
        'employee_id',
        'destination',
        'purpose',
        'transportation_type',
        'plate_number',
        'fuel_consumption',
        'fuel_type',
        'departure_datetime',
        'return_datetime',
        'total_days',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'is_liquidated',
    ];

    protected $casts = [
        'departure_datetime' => 'datetime',
        'return_datetime' => 'datetime',
        'approved_at' => 'datetime',
        'is_liquidated' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE
    |--------------------------------------------------------------------------
    */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'employee_id',
            'employee_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVER
    |--------------------------------------------------------------------------
    */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'approved_by',
            'employee_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DESTINATIONS
    |--------------------------------------------------------------------------
    */
    public function destinations(): HasMany
    {
        return $this->hasMany(
            TravelDestination::class,
            'travel_request_id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIQUIDATION
    |--------------------------------------------------------------------------
    */
    public function liquidation(): HasOne
    {
        return $this->hasOne(
            TravelLiquidation::class,
            'travel_request_id',
            'id'
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
            'travel_request_id'
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
            'travel_request_id'
        );
    }
}