<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyTransferItem extends Model
{
    protected $table = 'property_transfer_items';

    protected $fillable = ['transfer_id', 'property_item_id', 'remarks'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(PropertyTransfer::class, 'transfer_id');
    }

    public function propertyItem(): BelongsTo
    {
        return $this->belongsTo(PropertyItem::class, 'property_item_id');
    }
}
