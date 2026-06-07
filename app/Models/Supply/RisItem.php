<?php

namespace App\Models\Supply;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RisItem extends Model
{
    protected $table = 'ris_items';

    protected $fillable = [
        'ris_id', 'supply_item_id', 'quantity_requested', 'quantity_issued', 'unit_cost', 'remarks',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_issued'    => 'decimal:3',
        'unit_cost'          => 'decimal:4',
    ];

    public function ris(): BelongsTo
    {
        return $this->belongsTo(RequisitionIssueSlip::class, 'ris_id');
    }

    public function supplyItem(): BelongsTo
    {
        return $this->belongsTo(SupplyItem::class, 'supply_item_id');
    }

    public function totalCost(): float
    {
        return round((float) $this->quantity_issued * (float) $this->unit_cost, 2);
    }

    public function isFullyIssued(): bool
    {
        return (float) $this->quantity_issued >= (float) $this->quantity_requested;
    }
}
