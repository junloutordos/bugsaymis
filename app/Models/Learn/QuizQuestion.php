<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $table = 'learn_quiz_questions';

    protected $fillable = ['learn_quiz_id', 'question_type', 'prompt', 'points', 'position', 'difficulty'];

    protected $casts = [
        'points' => 'decimal:2',
        'position' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'learn_quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class, 'learn_quiz_question_id')->orderBy('position');
    }

    public function acceptedAnswers(): HasMany
    {
        return $this->hasMany(QuizQuestionAcceptedAnswer::class, 'learn_quiz_question_id');
    }
}
