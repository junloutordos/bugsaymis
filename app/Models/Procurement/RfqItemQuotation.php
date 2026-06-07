<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqItemQuotation extends Model
{
    protected $table = 'rfq_item_quotations';

    protected $fillable = [
        'rfq_supplier_id', 'rfq_item_id',
        'unit_cost', 'total_cost', 'remarks', 'is_awarded',
    ];

    protected $casts = [
        'unit_cost'  => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_awarded' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(RfqSupplier::class, 'rfq_supplier_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class, 'rfq_item_id');
    }
}
