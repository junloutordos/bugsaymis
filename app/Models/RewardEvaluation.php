<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardEvaluation extends Model
{
    protected $fillable = [
        'nomination_id',
        'evaluator_id',
        'score',
        'remarks',
        'evaluation_stage',
        'criterion_scores',
    ];

    protected $casts = [
        'criterion_scores' => 'array',
        'score'            => 'integer',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(RewardNomination::class, 'nomination_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
