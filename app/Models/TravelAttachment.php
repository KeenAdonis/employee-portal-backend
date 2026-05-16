<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAttachment extends Model
{
    protected $table = 'tb_travel_attachments';

    protected $fillable = [
        'travel_request_id',
        'travel_liquidation_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
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
    | UPLOADER
    |--------------------------------------------------------------------------
    */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            Employee::class,
            'uploaded_by'
        );
    }
}