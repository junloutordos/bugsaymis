<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttemptAnswer extends Model
{
    protected $table = 'learn_quiz_attempt_answers';

    protected $fillable = [
        'learn_quiz_attempt_id', 'learn_quiz_question_id', 'answer_text',
        'is_correct', 'points_earned', 'graded_at', 'graded_by',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'learn_quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'learn_quiz_question_id');
    }

    public function selectedOptions(): HasMany
    {
        return $this->hasMany(QuizAttemptSelectedOption::class, 'learn_quiz_attempt_answer_id');
    }
}
