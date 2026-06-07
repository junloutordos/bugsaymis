<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyStockCard extends Model
{
    protected $table = 'supply_stock_cards';

    protected $fillable = ['supply_item_id', 'balance_quantity', 'average_unit_cost'];

    protected $casts = [
        'balance_quantity'   => 'decimal:3',
        'average_unit_cost'  => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }

    public function totalValue(): float
    {
        return round((float) $this->balance_quantity * (float) $this->average_unit_cost, 2);
    }
}
