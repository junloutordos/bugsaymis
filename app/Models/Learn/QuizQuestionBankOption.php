<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionBankOption extends Model
{
    protected $table = 'learn_quiz_question_bank_options';

    protected $fillable = ['learn_quiz_question_bank_item_id', 'option_text', 'is_correct', 'position'];

    protected $casts = ['is_correct' => 'boolean', 'position' => 'integer'];

    public function bankItem(): BelongsTo
    {
        return $this->belongsTo(QuizQuestionBankItem::class, 'learn_quiz_question_bank_item_id');
    }
}
