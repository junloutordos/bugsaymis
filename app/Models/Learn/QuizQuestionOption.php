<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionOption extends Model
{
    protected $table = 'learn_quiz_question_options';

    protected $fillable = ['learn_quiz_question_id', 'option_text', 'is_correct', 'position'];

    protected $casts = [
        'is_correct' => 'boolean',
        'position' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'learn_quiz_question_id');
    }
}
