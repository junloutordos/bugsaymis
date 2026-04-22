<?php

namespace App\Models\PPMP;

use Illuminate\Database\Eloquent\Model;

class PpmpItem extends Model
{
    protected $table = 'ppmp_items';

    public const MONTHS = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];

    public const CATEGORIES = ['goods', 'infrastructure', 'consulting_services'];

    public const CATEGORY_LABELS = [
        'goods'               => 'Goods',
        'infrastructure'      => 'Infrastructure Projects',
        'consulting_services' => 'Consulting Services',
    ];

    public const PROCUREMENT_METHODS = [
        'competitive_bidding'    => 'Competitive Bidding',
        'limited_source_bidding' => 'Limited Source Bidding',
        'direct_contracting'     => 'Direct Contracting',
        'repeat_order'           => 'Repeat Order',
        'shopping'               => 'Shopping',
        'negotiated_procurement' => 'Negotiated Procurement',
        'agency_to_agency'       => 'Agency-to-Agency',
    ];

    protected $fillable = [
        'ppmp_id', 'code', 'description', 'unit', 'category', 'unit_cost',
        'jan', 'feb', 'mar', 'apr', 'may', 'jun',
        'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
        'procurement_method', 'is_ps_dbm', 'remarks', 'sort_order',
    ];

    protected $casts = [
        'unit_cost'      => 'decimal:2',
        'total_cost'     => 'decimal:2',
        'total_quantity' => 'integer',
        'is_ps_dbm'      => 'boolean',
    ];

    // ─── Auto-compute totals on save ──────────────────────────────────────────

    protected static function booted(): void
    {
        static::saving(function (PpmpItem $item) {
            $item->total_quantity = collect(self::MONTHS)->sum(fn ($m) => (int) $item->$m);
            $item->total_cost = $item->total_quantity * $item->unit_cost;
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function ppmp()
    {
        return $this->belongsTo(Ppmp::class, 'ppmp_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function monthlyQuantities(): array
    {
        return collect(self::MONTHS)->mapWithKeys(fn ($m) => [$m => (int) $this->$m])->all();
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function procurementMethodLabel(): string
    {
        return self::PROCUREMENT_METHODS[$this->procurement_method] ?? $this->procurement_method;
    }
}
