<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricScore extends Model
{
    protected $table = 'learn_rubric_scores';

    protected $fillable = ['learn_submission_id', 'learn_rubric_criterion_id', 'points_earned'];

    protected $casts = [
        'points_earned' => 'decimal:2',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'learn_submission_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'learn_rubric_criterion_id');
    }
}
