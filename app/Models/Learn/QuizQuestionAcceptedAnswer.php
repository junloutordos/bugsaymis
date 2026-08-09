<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionAcceptedAnswer extends Model
{
    protected $table = 'learn_quiz_question_accepted_answers';

    protected $fillable = ['learn_quiz_question_id', 'answer_text'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'learn_quiz_question_id');
    }
}
