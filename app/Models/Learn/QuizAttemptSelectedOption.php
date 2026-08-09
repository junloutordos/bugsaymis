<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptSelectedOption extends Model
{
    protected $table = 'learn_quiz_attempt_selected_options';

    protected $fillable = ['learn_quiz_attempt_answer_id', 'learn_quiz_question_option_id'];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(QuizAttemptAnswer::class, 'learn_quiz_attempt_answer_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuizQuestionOption::class, 'learn_quiz_question_option_id');
    }
}
