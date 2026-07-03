<?php

namespace App\Models\Quiz;

use App\Models\AMS\Activity;
use App\Models\ClassRecord\ClassRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    public const SOURCE_ACTIVITY = 'activity';
    public const SOURCE_CLASS_RECORD = 'class_record';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'cover_image',
        'source_type',
        'source_id',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function coverImageUrl(): ?string
    {
        return $this->cover_image ? route('quiz.cover', $this) : null;
    }

    /**
     * Resolves the polymorphic soft-referenced source model — quizzes can be
     * standalone or scoped to an AMS Activity / Class Record.
     */
    public function sourceModel(): Activity|ClassRecord|null
    {
        return match ($this->source_type) {
            self::SOURCE_ACTIVITY => Activity::find($this->source_id),
            self::SOURCE_CLASS_RECORD => ClassRecord::find($this->source_id),
            default => null,
        };
    }
}
