<?php

namespace App\Models\Procurement;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rfq extends Model
{
    const STATUS_DRAFT     = 'draft';
    const STATUS_OPEN      = 'open';
    const STATUS_CLOSED    = 'closed';
    const STATUS_AWARDED   = 'awarded';
    const STATUS_CANCELLED = 'cancelled';

    protected $table = 'rfqs';

    protected $fillable = [
        'rfq_number', 'procurement_id', 'rfq_date', 'validity_days',
        'delivery_place', 'payment_terms', 'purpose', 'status',
        'created_by', 'closed_at', 'awarded_at',
    ];

    protected $casts = [
        'rfq_date'   => 'date',
        'closed_at'  => 'datetime',
        'awarded_at' => 'datetime',
    ];

    public function pr(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class)->orderBy('item_no');
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(RfqSupplier::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'Draft',
            self::STATUS_OPEN      => 'Open — Awaiting Quotations',
            self::STATUS_CLOSED    => 'Closed — Evaluating',
            self::STATUS_AWARDED   => 'Awarded',
            self::STATUS_CANCELLED => 'Cancelled',
            default                => ucfirst($this->status),
        };
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $seq   = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;

        return sprintf('RFQ-%s-%s-%04d', $year, $month, $seq);
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function allQuotationsReceived(): bool
    {
        return $this->suppliers()->whereNotNull('quotation_received_at')->count() > 0
            && $this->suppliers()->whereNull('quotation_received_at')->count() === 0;
    }
}
