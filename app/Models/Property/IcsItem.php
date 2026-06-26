<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IcsItem extends Model
{
    protected $table = 'ics_items';

    protected $fillable = [
        'ics_id', 'property_item_id', 'description', 'unit',
        'quantity', 'unit_cost', 'estimated_useful_life', 'remarks',
    ];

    protected $casts = [
        'quantity'              => 'decimal:3',
        'unit_cost'             => 'decimal:4',
        'estimated_useful_life' => 'date:Y-m-d',
    ];

    public function ics(): BelongsTo
    {
        return $this->belongsTo(InventoryCustodianSlip::class, 'ics_id');
    }

    public function propertyItem(): BelongsTo
    {
        return $this->belongsTo(PropertyItem::class, 'property_item_id');
    }

    public function totalCost(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }
}
