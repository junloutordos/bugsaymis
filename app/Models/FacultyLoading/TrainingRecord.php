<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRecord extends Model
{
    protected $table = 'training_records';

    protected $fillable = [
        'user_id',
        'school_year_id',
        'training_title',
        'organizer',
        'venue',
        'date_from',
        'date_to',
        'level',
        'hours_earned',
        'load_units',
        'status',
        'verified_by',
        'verified_at',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'date_from'    => 'date:Y-m-d',
        'date_to'      => 'date:Y-m-d',
        'hours_earned' => 'decimal:2',
        'load_units'   => 'decimal:2',
        'verified_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Duration in calendar days (inclusive). */
    public function getDurationDaysAttribute(): int
    {
        return $this->date_from->diffInDays($this->date_to) + 1;
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
