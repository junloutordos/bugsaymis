<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $table = 'learn_quiz_attempts';

    protected $fillable = [
        'learn_quiz_id', 'student_id', 'attempt_number', 'question_order',
        'started_at', 'submitted_at', 'auto_submitted', 'score',
    ];

    protected $casts = [
        'question_order' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'auto_submitted' => 'boolean',
        'score' => 'decimal:2',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'learn_quiz_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class, 'learn_quiz_attempt_id');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
