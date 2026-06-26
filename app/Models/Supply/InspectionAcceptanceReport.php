<?php

namespace App\Models\Supply;

use App\Models\Procurement\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionAcceptanceReport extends Model
{
    protected $table = 'inspection_acceptance_reports';

    protected $fillable = [
        'iar_number', 'purchase_order_id', 'supplier_name', 'supplier_address',
        'supplier_tin', 'delivery_date', 'inspection_date', 'inspector_id',
        'property_officer_id', 'status', 'remarks', 'accepted_at', 'created_by',
    ];

    protected $casts = [
        'delivery_date'   => 'date:Y-m-d',
        'inspection_date' => 'date:Y-m-d',
        'accepted_at'     => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function propertyOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'property_officer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IarItem::class, 'iar_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'inspected' => 'Inspected',
            'accepted'  => 'Accepted',
            'partial'   => 'Partially Accepted',
            default     => ucfirst($this->status),
        };
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function totalAccepted(): float
    {
        return (float) $this->items->sum(fn ($i) => $i->quantity_accepted * $i->unit_cost);
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $seq   = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return sprintf('IAR-%s-%s-%04d', $year, $month, $seq);
    }
}
