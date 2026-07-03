<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelFlightAuthority extends Model
{
    protected $fillable = [
        'travel_request_id',
        'status',
        'passenger_name',
        'route',
        'preferred_departure_date',
        'preferred_return_date',
        'estimated_airfare',
        'booking_notes',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'preferred_departure_date' => 'date:Y-m-d',
        'preferred_return_date' => 'date:Y-m-d',
        'estimated_airfare' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
