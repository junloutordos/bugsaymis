<?php

namespace App\Models\SALN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnPersonalProperty extends Model
{
    protected $table = 'saln_personal_properties';

    protected $fillable = [
        'saln_record_id',
        'description',
        'category',
        'year_acquired',
        'acquisition_cost',
        'owner',
    ];

    protected $casts = [
        'acquisition_cost' => 'decimal:2',
        'year_acquired'    => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function salnRecord(): BelongsTo
    {
        return $this->belongsTo(SalnRecord::class, 'saln_record_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getCategoryLabel(): string
    {
        return match ($this->category) {
            'motor_vehicle'   => 'Motor Vehicle',
            'jewelry'         => 'Jewelry / Accessories',
            'artwork'         => 'Artwork / Collectibles',
            'shares_of_stock' => 'Shares of Stock',
            'cash_on_hand'    => 'Cash on Hand',
            'bank_deposits'   => 'Bank Deposits',
            'receivables'     => 'Receivables',
            'others'          => 'Others',
            default           => ucfirst($this->category),
        };
    }

    /** Value used in net worth computation (CSC uses acquisition cost for personal properties) */
    public function getValueForTotals(): float
    {
        return (float) $this->acquisition_cost;
    }
}
