<?php

namespace App\Models\Quiz;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    public const TYPE_MCQ = 'mcq';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_POLL = 'poll';
    public const TYPE_OPEN_ENDED = 'open_ended';

    public const SCORED_TYPES = [self::TYPE_MCQ, self::TYPE_TRUE_FALSE, self::TYPE_CHECKBOX];

    protected $fillable = [
        'quiz_id',
        'sort_order',
        'type',
        'question_text',
        'image',
        'time_limit_seconds',
        'points_base',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class, 'question_id')->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizPlayerAnswer::class, 'question_id');
    }

    public function isScored(): bool
    {
        return in_array($this->type, self::SCORED_TYPES, true);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? route('quiz.questions.image', $this) : null;
    }
}
