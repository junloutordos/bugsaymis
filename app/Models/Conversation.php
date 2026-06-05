<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot(['last_read_at', 'left_at', 'archived_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->withTrashed();
    }

    public function activeMessages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    /**
     * The single most-recent message — used for conversation list preview.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Conversations the given user is a participant of.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('participants', fn ($q) => $q->where('users.id', $userId));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Find an existing direct conversation between exactly these two users,
     * or return null. Used to avoid creating duplicate DMs.
     */
    public static function directBetween(int $userA, int $userB): ?self
    {
        return self::where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userA))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $userB))
            ->first();
    }

    /**
     * Unread message count for a specific user in this conversation.
     */
    public function unreadCountFor(int $userId): int
    {
        $pivot = $this->participants
            ->firstWhere('id', $userId)
            ?->pivot;

        $query = $this->hasMany(Message::class)
            ->where('sender_id', '!=', $userId);

        if ($pivot?->last_read_at) {
            $query->where('created_at', '>', $pivot->last_read_at);
        }

        return $query->count();
    }
}
