<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelDestination extends Model
{
    protected $table = 'tb_travel_destinations';

    protected $fillable = [
        'travel_request_id',
        'sequence_no',
        'location',
        'remarks',
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
}