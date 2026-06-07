<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParItem extends Model
{
    protected $table = 'par_items';

    protected $fillable = [
        'par_id', 'property_item_id', 'description',
        'unit', 'quantity', 'unit_cost', 'remarks',
    ];

    protected $casts = [
        'quantity'  => 'decimal:3',
        'unit_cost' => 'decimal:4',
    ];

    public function par(): BelongsTo
    {
        return $this->belongsTo(PropertyAcknowledgmentReceipt::class, 'par_id');
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
