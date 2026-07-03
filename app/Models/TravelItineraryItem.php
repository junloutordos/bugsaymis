<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelItineraryItem extends Model
{
    protected $fillable = [
        'travel_request_id',
        'sort_order',
        'travel_date',
        'places_to_visit',
        'departure_time',
        'arrival_time',
        'means_of_transportation',
        'transportation_cost',
        'per_diem',
        'other_cost',
        'remarks',
    ];

    protected $casts = [
        'travel_date' => 'date:Y-m-d',
        'transportation_cost' => 'decimal:2',
        'per_diem' => 'decimal:2',
        'other_cost' => 'decimal:2',
    ];

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function total(): float
    {
        return (float) $this->transportation_cost + (float) $this->per_diem + (float) $this->other_cost;
    }
}
