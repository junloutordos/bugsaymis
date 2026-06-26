<?php

namespace App\Models\Procurement;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_number',
        'po_date',
        'procurement_id',
        'supplier_name',
        'supplier_address',
        'supplier_tin',
        'delivery_place',
        'delivery_date',
        'payment_terms',
        'delivery_terms',
        'total_amount',
        'status',
        'created_by',
        'procurement_officer_id',
        'procurement_officer_at',
        'procurement_officer_remarks',
        'ocd_id',
        'ocd_at',
        'issued_at',
        'remarks',
    ];

    protected $casts = [
        'po_date'                   => 'date:Y-m-d',
        'delivery_date'             => 'date:Y-m-d',
        'total_amount'              => 'decimal:2',
        'procurement_officer_at'    => 'datetime',
        'ocd_at'                    => 'datetime',
        'issued_at'                 => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function pr(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function procurementOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'procurement_officer_id');
    }

    public function ocd(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ocd_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('item_no');
    }

    public function obligationRequests(): HasMany
    {
        return $this->hasMany(OblRequest::class, 'purchase_order_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft'                => 'Draft',
            'pending_procurement'  => 'Pending Procurement Officer',
            'pending_ocd'          => 'Pending Campus Director',
            'issued'               => 'Issued',
            'cancelled'            => 'Cancelled',
            default                => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function recalculateTotal(): void
    {
        $this->update([
            'total_amount' => $this->items()->sum('total_cost'),
        ]);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeForPr($query, int $prId)
    {
        return $query->where('procurement_id', $prId);
    }
}
