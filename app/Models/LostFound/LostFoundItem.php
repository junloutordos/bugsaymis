<?php

namespace App\Models\LostFound;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LostFoundItem extends Model
{
    protected $table = 'lost_found_items';

    public const CATEGORIES = [
        'electronics'  => 'Electronics / Gadgets',
        'wallet_id'    => 'Wallet / ID / Cards',
        'clothing'     => 'Clothing / Accessories',
        'books'        => 'Books / School Supplies',
        'keys'         => 'Keys',
        'bag'          => 'Bags / Containers',
        'sports'       => 'Sports Equipment',
        'other'        => 'Other',
    ];

    public const LOST_STATUSES  = ['open', 'matched', 'claimed', 'closed'];
    public const FOUND_STATUSES = ['pending_turnover', 'in_custody', 'claimed', 'disposed'];

    protected $fillable = [
        'type',
        'item_name',
        'description',
        'category',
        'location',
        'date_lost_found',
        'photo_path',
        'reporter_user_id',
        'reporter_student_id',
        'reporter_name',
        'status',
        'storage_location',
        'matched_item_id',
        'turned_over_at',
        'received_by',
        'claimed_at',
        'claimed_by_name',
        'released_by',
    ];

    protected $casts = [
        'date_lost_found' => 'date:Y-m-d',
        'turned_over_at'  => 'datetime',
        'claimed_at'      => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function reporterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reporterStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'reporter_student_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function matchedItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'matched_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(LostFoundMovement::class)->orderBy('created_at');
    }

    public function honestyPoint(): HasOne
    {
        return $this->hasOne(HonestyPoint::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Display name of whoever reported/found the item. */
    public function getReporterDisplayNameAttribute(): string
    {
        if ($this->reporter_user_id) {
            return $this->reporterUser?->name ?? 'Employee';
        }
        if ($this->reporter_student_id) {
            return $this->reporterStudent?->full_name ?? 'Student';
        }

        return $this->reporter_name ?? 'Anonymous';
    }

    /** Human-readable "where is this item right now". */
    public function getCustodyLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_turnover' => 'With finder — awaiting turnover to GSU',
            'in_custody'       => 'In GSU custody' . ($this->storage_location ? " ({$this->storage_location})" : ''),
            'claimed'          => 'Claimed by owner',
            'disposed'         => 'Disposed / donated',
            'open'             => 'Still missing',
            'matched'          => 'Match found — for claiming at GSU',
            'closed'           => 'Report closed',
            default            => $this->status,
        };
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeFoundItems($query)
    {
        return $query->where('type', 'found');
    }

    public function scopeLostReports($query)
    {
        return $query->where('type', 'lost');
    }

    public function scopeInCustody($query)
    {
        return $query->where('type', 'found')->where('status', 'in_custody');
    }
}
