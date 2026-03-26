<?php

namespace App\Models\SALN;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalnReview extends Model
{
    protected $table = 'saln_reviews';

    /**
     * Audit rows are immutable — no updated_at column exists.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'saln_record_id',
        'actor_id',
        'action',
        'remarks',
        'status_from',
        'status_to',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function salnRecord(): BelongsTo
    {
        return $this->belongsTo(SalnRecord::class, 'saln_record_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created'   => 'Draft created',
            'updated'   => 'Draft updated',
            'submitted' => 'Submitted for review',
            'reviewed'  => 'Reviewed by committee',
            'approved'  => 'Approved',
            'returned'  => 'Returned for revision',
            'filed'     => 'Filed by HR',
            'reopened'  => 'Reopened',
            default     => ucfirst($this->action),
        };
    }

    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'created'   => 'pencil',
            'updated'   => 'pencil',
            'submitted' => 'paper-airplane',
            'reviewed'  => 'eye',
            'approved'  => 'check-circle',
            'returned'  => 'arrow-uturn-left',
            'filed'     => 'archive-box',
            'reopened'  => 'lock-open',
            default     => 'information-circle',
        };
    }
}
