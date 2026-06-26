<?php

namespace App\Models\Property;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyTransfer extends Model
{
    protected $table = 'property_transfers';

    protected $fillable = [
        'transfer_number', 'from_officer_id', 'to_officer_id',
        'transfer_date', 'reason', 'status', 'remarks', 'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date:Y-m-d',
    ];

    public function fromOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_officer_id');
    }

    public function toOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_officer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PropertyTransferItem::class, 'transfer_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'   => 'Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default     => ucfirst($this->status),
        };
    }

    public static function generateNumber(): string
    {
        $year  = now()->format('Y');
        $seq   = static::whereYear('created_at', $year)->count() + 1;
        return sprintf('XFER-%s-%04d', $year, $seq);
    }
}
