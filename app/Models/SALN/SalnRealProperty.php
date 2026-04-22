<?php

namespace App\Models\SALN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnRealProperty extends Model
{
    protected $table = 'saln_real_properties';

    protected $fillable = [
        'saln_record_id',
        'kind',
        'exact_location',
        'assessed_value',
        'current_fair_market_value',
        'year_acquired',
        'acquisition_mode',
        'acquisition_mode_other',
        'acquisition_cost',
        'owner',
    ];

    protected $casts = [
        'assessed_value'            => 'decimal:2',
        'current_fair_market_value' => 'decimal:2',
        'acquisition_cost'          => 'decimal:2',
        'year_acquired'             => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function salnRecord(): BelongsTo
    {
        return $this->belongsTo(SalnRecord::class, 'saln_record_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Label used on the printed SALN form */
    public function getAcquisitionModeLabel(): string
    {
        return match ($this->acquisition_mode) {
            'purchased'  => 'Purchased',
            'inherited'  => 'Inherited',
            'gift'       => 'Gift',
            'others'     => $this->acquisition_mode_other ?? 'Others',
            default      => ucfirst($this->acquisition_mode),
        };
    }

    /** Value used in net worth computation (CSC uses current FMV for real properties) */
    public function getValueForTotals(): float
    {
        return (float) $this->current_fair_market_value;
    }
}
