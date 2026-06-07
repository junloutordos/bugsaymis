<?php

namespace App\Models\Property;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BsrItem extends Model
{
    protected $table = 'bsr_items';

    protected $fillable = [
        'bsr_id', 'property_item_id', 'condition',
        'recommendation', 'appraised_value', 'remarks',
    ];

    protected $casts = [
        'appraised_value' => 'decimal:2',
    ];

    public function bsr(): BelongsTo
    {
        return $this->belongsTo(BoardOfSurveyReport::class, 'bsr_id');
    }

    public function propertyItem(): BelongsTo
    {
        return $this->belongsTo(PropertyItem::class, 'property_item_id');
    }
}
