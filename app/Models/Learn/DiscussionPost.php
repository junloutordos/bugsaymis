<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionPost extends Model
{
    protected $table = 'learn_discussion_posts';

    protected $fillable = [
        'learn_discussion_id', 'parent_post_id', 'author_type', 'author_id', 'body',
        'is_deleted', 'deleted_by_type', 'deleted_by_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'learn_discussion_id');
    }

    public function parentPost(): BelongsTo
    {
        return $this->belongsTo(DiscussionPost::class, 'parent_post_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'parent_post_id')->orderBy('created_at');
    }

    public function isDeleted(): bool
    {
        return (bool) $this->is_deleted;
    }
}
