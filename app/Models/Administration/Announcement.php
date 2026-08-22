<?php

namespace App\Models\Administration;

use App\Models\User;
use App\Traits\HasNoticeAcknowledgments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    use HasNoticeAcknowledgments;

    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'body',
        'poster_path',
        'audience',
        'status',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function targets(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_user');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Published announcements addressed to everyone, all employees, or specifically to $user. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q
                ->whereIn('audience', ['all', 'employees'])
                ->orWhereHas('targets', fn ($t) => $t->where('users.id', $user->id)));
    }

    /** Published announcements addressed to everyone or to the given non-employee group (students|parents). */
    public function scopeVisibleToAudienceGroup(Builder $query, string $group): Builder
    {
        return $query->where('status', 'published')
            ->whereIn('audience', ['all', $group]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
