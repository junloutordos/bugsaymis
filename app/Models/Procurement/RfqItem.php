<?php

namespace App\Models\Procurement;

use App\Models\ProcurementItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqItem extends Model
{
    protected $table = 'rfq_items';

    protected $fillable = [
        'rfq_id', 'procurement_item_id', 'item_no',
        'unit', 'description', 'quantity', 'abc',
    ];

    protected $casts = [
        'abc'      => 'decimal:2',
        'quantity' => 'integer',
        'item_no'  => 'integer',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class, 'procurement_item_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(RfqItemQuotation::class);
    }

    public function lowestQuotation(): ?RfqItemQuotation
    {
        return $this->quotations()
            ->where('unit_cost', '>', 0)
            ->orderBy('unit_cost')
            ->first();
    }

    public function awardedQuotation(): ?RfqItemQuotation
    {
        return $this->quotations()->where('is_awarded', true)->first();
    }
}
