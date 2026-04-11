<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable snapshot of a signatory as they appeared on a printed form
 * at the time of document generation.
 *
 * Captures name, position, division, office, and signature image path —
 * all locked at capture time regardless of future organisational changes.
 */
class SignatorySnapshot extends Model
{
    const UPDATED_AT = null;

    protected $table = 'signatory_snapshots';

    protected $fillable = [
        'signable_type',
        'signable_id',
        'role_label',
        'user_id',
        'name_snapshot',
        'position_snapshot',
        'division_snapshot',
        'office_snapshot',
        'signature_path',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Immutability Guard ─────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException(
                'SignatorySnapshot records are immutable and cannot be updated after creation.'
            );
        });

        static::deleting(function () {
            throw new \RuntimeException(
                'SignatorySnapshot records cannot be deleted — they form the legal document trail.'
            );
        });
    }

    // ── Convenience ───────────────────────────────────────────────────────────

    /** Returns the snapshot as an array suitable for Inertia props. */
    public function toSignatoryArray(): array
    {
        return [
            'name'           => $this->name_snapshot,
            'position'       => $this->position_snapshot,
            'division'       => $this->division_snapshot,
            'office'         => $this->office_snapshot,
            'signature_path' => $this->signature_path,
            'captured_at'    => $this->captured_at?->toDateTimeString(),
        ];
    }
}
