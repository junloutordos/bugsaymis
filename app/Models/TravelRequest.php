<?php

namespace App\Models;

use App\Models\Procurement\DisbursementVoucher;
use App\Models\Procurement\OblRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TravelRequest extends Model
{
    protected $fillable = [
        'control_no',
        'traveler_id',
        'created_by',
        'division_id',
        'division_chief_id',
        'travel_type',
        'funding_source',
        'fund_cluster',
        'travel_mode',
        'origin',
        'destination',
        'purpose',
        'start_date',
        'end_date',
        'passenger_count',
        'requires_cash_advance',
        'cash_advance_amount',
        'cash_advance_status',
        'liquidation_status',
        'liquidation_remarks',
        'travel_order_issuance_id',
        'special_order_issuance_id',
        'vehicle_request_id',
        'ors_id',
        'dv_id',
        'status',
        'return_reason',
        'submitted_by',
        'submitted_at',
        'division_approved_by',
        'division_approved_at',
        'fad_reviewed_by',
        'fad_reviewed_at',
        'ocd_approved_by',
        'ocd_approved_at',
        'completed_at',
        'liquidated_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'requires_cash_advance' => 'boolean',
        'cash_advance_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'division_approved_at' => 'datetime',
        'fad_reviewed_at' => 'datetime',
        'ocd_approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'liquidated_at' => 'datetime',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'division_approved' => 'Division Approved',
        'fad_reviewed' => 'FAD Reviewed',
        'ocd_approved' => 'OCD Approved',
        'transport_arranged' => 'Transport Arranged',
        'cash_advance_processing' => 'Cash Advance Processing',
        'dv_created' => 'DV Created',
        'released' => 'Released',
        'completed' => 'Completed',
        'liquidated' => 'Liquidated',
        'returned' => 'Returned',
        'cancelled' => 'Cancelled',
    ];

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traveler_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function divisionChief(): BelongsTo
    {
        return $this->belongsTo(User::class, 'division_chief_id');
    }

    public function travelOrderIssuance(): BelongsTo
    {
        return $this->belongsTo(Issuance::class, 'travel_order_issuance_id');
    }

    public function specialOrderIssuance(): BelongsTo
    {
        return $this->belongsTo(Issuance::class, 'special_order_issuance_id');
    }

    public function vehicleRequest(): BelongsTo
    {
        return $this->belongsTo(VehicleRequest::class);
    }

    public function ors(): BelongsTo
    {
        return $this->belongsTo(OblRequest::class, 'ors_id');
    }

    public function dv(): BelongsTo
    {
        return $this->belongsTo(DisbursementVoucher::class, 'dv_id');
    }

    public function itineraryItems(): HasMany
    {
        return $this->hasMany(TravelItineraryItem::class)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TravelAttachment::class)->latest();
    }

    public function flightAuthority(): HasOne
    {
        return $this->hasOne(TravelFlightAuthority::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }
}
