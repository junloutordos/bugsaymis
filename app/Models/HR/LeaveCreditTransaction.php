<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LeaveCreditTransaction extends Model
{
    protected $table = 'leave_credit_transactions';

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'year',
        'type',
        'amount',
        'balance_after',
        'referenceable_type',
        'referenceable_id',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:4',
        'balance_after' => 'decimal:4',
        'year'          => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeOfType($query, string|array $type)
    {
        return $query->whereIn('type', (array) $type);
    }
}
