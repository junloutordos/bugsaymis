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
        'svp'                    => 'Small Value Procurement (SVP)',
        'negotiated_procurement' => 'Negotiated Procurement',
        'agency_to_agency'       => 'Agency-to-Agency',
        'emergency'              => 'Emergency Procurement',
        'lease_real_property'    => 'Lease of Real Property',
        'scientific_scholarly'   => 'Scientific/Scholarly/Artistic Works',
        'two_failed_biddings'    => 'Two-Failed Biddings',
    ];

    public const FUND_SOURCES = [
        'mooe'       => 'MOOE',
        'co'         => 'CO',
        'ps'         => 'PS',
        'trust_fund' => 'Trust Fund',
        'sef'        => 'SEF',
    ];

    public const QUARTERS = [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'];

    protected $fillable = [
        'ppmp_id', 'code', 'description', 'unit', 'category', 'unit_cost',
        'jan', 'feb', 'mar', 'apr', 'may', 'jun',
        'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
        'procurement_method', 'is_ps_dbm', 'remarks', 'sort_order',
        'fund_source', 'procurement_quarter', 'delivery_quarter',
        'price_source', 'price_validity_date',
    ];

    protected $casts = [
        'unit_cost'           => 'decimal:2',
        'total_cost'          => 'decimal:2',
        'total_quantity'      => 'integer',
        'is_ps_dbm'           => 'boolean',
        'procurement_quarter' => 'integer',
        'delivery_quarter'    => 'integer',
        'price_validity_date' => 'date',
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

    public function fundSourceLabel(): string
    {
        return self::FUND_SOURCES[$this->fund_source] ?? strtoupper($this->fund_source ?? 'MOOE');
    }

    public function procurementQuarterLabel(): ?string
    {
        return $this->procurement_quarter ? self::QUARTERS[$this->procurement_quarter] : null;
    }

    public function deliveryQuarterLabel(): ?string
    {
        return $this->delivery_quarter ? self::QUARTERS[$this->delivery_quarter] : null;
    }
}
