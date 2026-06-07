<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IarItem extends Model
{
    protected $table = 'iar_items';

    protected $fillable = [
        'iar_id', 'supply_item_id', 'description', 'unit',
        'quantity_ordered', 'quantity_delivered', 'quantity_accepted',
        'quantity_rejected', 'unit_cost', 'rejection_reason', 'is_posted',
    ];

    protected $casts = [
        'quantity_ordered'   => 'decimal:3',
        'quantity_delivered' => 'decimal:3',
        'quantity_accepted'  => 'decimal:3',
        'quantity_rejected'  => 'decimal:3',
        'unit_cost'          => 'decimal:4',
        'is_posted'          => 'boolean',
    ];

    public function iar(): BelongsTo
    {
        return $this->belongsTo(InspectionAcceptanceReport::class, 'iar_id');
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }

    public function totalCost(): float
    {
        return round((float) $this->quantity_accepted * (float) $this->unit_cost, 2);
    }
}
