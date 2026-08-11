<?php

namespace App\Models\HR;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Substitution extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'substitutions';

    public const STATUSES = [
        'pending_approval' => 'Pending Approval',
        'approved'         => 'Approved',
        'rejected'         => 'Rejected',
        'revoked'          => 'Revoked',
    ];

    protected $fillable = [
        'original_user_id',
        'substitute_user_id',
        'absentable_type',
        'absentable_id',
        'start_date',
        'end_date',
        'status',
        'nominated_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'revoked_by',
        'revoked_at',
        'revocation_reason',
        'notes',
    ];

    protected $casts = [
        'start_date'  => 'date:Y-m-d',
        'end_date'    => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_user_id');
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function absentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actingAsSessions(): HasMany
    {
        return $this->hasMany(ActingAsSession::class);
    }

    // ── Query Scopes ───────────────────────────────────────────────────────

    /** Grants that are still awaiting or hold approval (not rejected/revoked). */
    public function scopeApprovedOrPending($query)
    {
        return $query->whereIn('status', ['pending_approval', 'approved']);
    }

    /** Approved grants whose date window covers a given date (defaults to today). */
    public function scopeCurrent($query, ?string $date = null)
    {
        $date ??= now()->toDateString();

        return $query->where('status', 'approved')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isWithinWindow(?Carbon $date = null): bool
    {
        $date ??= now();

        return $this->status === 'approved'
            && $date->toDateString() >= $this->start_date->toDateString()
            && $date->toDateString() <= $this->end_date->toDateString();
    }
}
