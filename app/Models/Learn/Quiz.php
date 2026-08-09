<?php

namespace App\Models\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Quiz extends Model implements HasClassRecordLink
{
    protected $table = 'learn_quizzes';

    protected $fillable = [
        'title', 'instructions', 'time_limit_minutes', 'max_attempts', 'questions_to_draw',
        'shuffle_questions', 'shuffle_options', 'due_at', 'is_locked',
        'class_record_assessment_id', 'pushed_at',
    ];

    protected $casts = [
        'time_limit_minutes' => 'integer',
        'max_attempts' => 'integer',
        'questions_to_draw' => 'integer',
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'due_at' => 'datetime',
        'is_locked' => 'boolean',
        'pushed_at' => 'datetime',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'learn_quiz_id')->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'learn_quiz_id');
    }

    public function classRecordAssessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }

    /**
     * When questions_to_draw is set, every question on the quiz is required to carry equal
     * points (enforced at question-creation time) — so the deterministic max is draw-count ×
     * per-question points, even though each attempt only sees a random subset.
     */
    public function maxScore(): ?float
    {
        if ($this->questions()->count() === 0) {
            return null;
        }

        if ($this->questions_to_draw !== null) {
            $perQuestion = (float) $this->questions()->value('points');

            return $perQuestion * $this->questions_to_draw;
        }

        return (float) $this->questions()->sum('points');
    }

    /** The Learn course this quiz belongs to, resolved through its module item. */
    public function course(): ?Course
    {
        return $this->moduleItem?->module?->course;
    }

    public function canEdit(User $user): bool
    {
        return $this->course()?->canEdit($user) ?? false;
    }

    /**
     * @return array<int, float> student_id => highest finalized score across their attempts.
     */
    public function gradedStudentScores(): array
    {
        return $this->attempts()
            ->whereNotNull('score')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($attempts) => (float) $attempts->max('score'))
            ->all();
    }
}
