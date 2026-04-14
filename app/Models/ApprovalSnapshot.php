<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable record of a single approval action taken on any approvable model.
 *
 * IMPORTANT: This model has no updated_at. Once created it must never change.
 * The boot() guard enforces this at the ORM level.
 */
class ApprovalSnapshot extends Model
{
    /**
     * No updated_at column — immutability marker.
     */
    const UPDATED_AT = null;

    protected $table = 'approval_snapshots';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'step',
        'sequence',
        'action',
        'status',
        'user_id',
        'name_snapshot',
        'position_snapshot',
        'division_snapshot',
        'office_snapshot',
        'remarks',
        'signed_at',
    ];

    protected $casts = [
        'signed_at'  => 'datetime',
        'created_at' => 'datetime',
        'sequence'   => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    /** The record this snapshot belongs to (LeaveApplication, PayrollRun, etc.) */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Soft user reference. withTrashed() ensures the relation does not return
     * null simply because the user was soft-deleted later.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Immutability Guard ─────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException(
                'ApprovalSnapshot records are immutable and cannot be updated after creation.'
            );
        });

        static::deleting(function () {
            throw new \RuntimeException(
                'ApprovalSnapshot records cannot be deleted — they are part of the audit trail.'
            );
        });
    }

    // ── Convenience Accessors ─────────────────────────────────────────────────

    /** Human-readable label for the action taken. */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'certified' => 'Certified',
            'forwarded' => 'Forwarded',
            'approved'  => 'Approved',
            'rejected'  => 'Rejected',
            'filed'     => 'Filed',
            'reviewed'  => 'Reviewed',
            default     => ucfirst($this->action),
        };
    }

    /** CSS class hint for the action badge. */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'approved', 'certified', 'forwarded' => 'text-emerald-600',
            'rejected'                            => 'text-red-500',
            default                               => 'text-slate-500',
        };
    }
}
