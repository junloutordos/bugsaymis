<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment_path',
        'attachment_type',
        'read_at',
    ];

    protected $casts = [
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class)->withTrashed();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isReadBy(int $userId): bool
    {
        // For DMs: use the explicit read_at timestamp on the message
        if ($this->conversation?->type === 'direct') {
            return $this->read_at !== null;
        }

        // For groups: check the participant's last_read_at pivot
        $pivot = $this->conversation
            ?->participants
            ->firstWhere('id', $userId)
            ?->pivot;

        return $pivot && $pivot->last_read_at >= $this->created_at;
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }
}
