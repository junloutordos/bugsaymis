<?php

namespace App\Models\SALN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnBusinessInterest extends Model
{
    protected $table = 'saln_business_interests';

    protected $fillable = [
        'saln_record_id',
        'entity_name',
        'business_address',
        'nature_of_business',
        'date_acquired',
        'acquisition_cost',
        'present_fair_market_value',
        'owner',
    ];

    protected $casts = [
        'date_acquired'             => 'date:Y-m-d',
        'acquisition_cost'          => 'decimal:2',
        'present_fair_market_value' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function salnRecord(): BelongsTo
    {
        return $this->belongsTo(SalnRecord::class, 'saln_record_id');
    }
}
