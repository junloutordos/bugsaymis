<?php

namespace App\Models\Property;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyAcknowledgmentReceipt extends Model
{
    protected $table = 'property_acknowledgment_receipts';

    protected $fillable = [
        'par_number', 'issue_date', 'issued_by', 'received_by',
        'division_id', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date:Y-m-d',
    ];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ParItem::class, 'par_id');
    }

    public function totalAmount(): float
    {
        return (float) $this->items->sum(fn ($i) => $i->quantity * $i->unit_cost);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'active'     => 'Active',
            'returned'   => 'Returned',
            'transferred' => 'Transferred',
            'superseded' => 'Superseded',
            default      => ucfirst($this->status),
        };
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $month = now()->format('m');
        $seq   = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
        return sprintf('PAR-%s-%s-%04d', $year, $month, $seq);
    }
}
