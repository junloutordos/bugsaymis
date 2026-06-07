<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisposalItem extends Model
{
    protected $table = 'disposal_items';

    protected $fillable = [
        'disposal_id', 'property_item_id',
        'quantity', 'appraised_value', 'remarks',
    ];

    protected $casts = [
        'quantity'        => 'decimal:3',
        'appraised_value' => 'decimal:2',
    ];

    public function disposal(): BelongsTo
    {
        return $this->belongsTo(DisposalRecord::class, 'disposal_id');
    }

    public function propertyItem(): BelongsTo
    {
        return $this->belongsTo(PropertyItem::class, 'property_item_id');
    }
}
