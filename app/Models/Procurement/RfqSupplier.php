<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqSupplier extends Model
{
    protected $table = 'rfq_suppliers';

    protected $fillable = [
        'rfq_id', 'supplier_name', 'supplier_address', 'supplier_tin',
        'supplier_contact', 'sent_at', 'sent_by',
        'quotation_received_at', 'quotation_file_s3_key', 'quotation_filename',
        'quotation_total', 'remarks',
    ];

    protected $casts = [
        'sent_at'               => 'datetime',
        'quotation_received_at' => 'datetime',
        'quotation_total'       => 'decimal:2',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function itemQuotations(): HasMany
    {
        return $this->hasMany(RfqItemQuotation::class);
    }

    public function recalculateTotal(): void
    {
        $total = $this->itemQuotations()->where('unit_cost', '>', 0)->sum('total_cost');
        $this->update(['quotation_total' => $total ?: null]);
    }

    public function awardedItems(): HasMany
    {
        return $this->hasMany(RfqItemQuotation::class)->where('is_awarded', true);
    }
}
