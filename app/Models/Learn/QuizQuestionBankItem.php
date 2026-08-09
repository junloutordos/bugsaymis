<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestionBankItem extends Model
{
    protected $table = 'learn_quiz_question_bank_items';

    protected $fillable = ['user_id', 'name', 'question_type', 'prompt', 'points', 'difficulty'];

    protected $casts = ['points' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionBankOption::class, 'learn_quiz_question_bank_item_id')->orderBy('position');
    }
}
