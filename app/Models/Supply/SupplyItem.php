<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplyItem extends Model
{
    protected $table = 'supply_items';

    protected $fillable = [
        'item_code', 'description', 'unit', 'category_id',
        'estimated_unit_cost', 'reorder_point', 'reorder_quantity',
        'specifications', 'is_active',
    ];

    protected $casts = [
        'estimated_unit_cost' => 'decimal:4',
        'reorder_point'       => 'integer',
        'reorder_quantity'    => 'integer',
        'is_active'           => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplyCategory::class, 'category_id');
    }

    public function stockCard(): HasOne
    {
        return $this->hasOne(SupplyStockCard::class, 'supply_item_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplyLedgerEntry::class, 'supply_item_id')->orderBy('transaction_date')->orderBy('id');
    }

    public function currentBalance(): float
    {
        return (float) ($this->stockCard?->balance_quantity ?? 0);
    }

    public function averageUnitCost(): float
    {
        return (float) ($this->stockCard?->average_unit_cost ?? $this->estimated_unit_cost);
    }

    public function isLowStock(): bool
    {
        return $this->reorder_point > 0 && $this->currentBalance() <= $this->reorder_point;
    }

    public static function generateCode(int $categoryId): string
    {
        $prefix = 'ITEM';
        $seq = static::where('category_id', $categoryId)->count() + 1;
        return sprintf('%s-%03d-%04d', $prefix, $categoryId, $seq);
    }
}
