<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    protected $table = 'learn_rubric_criteria';

    protected $fillable = ['learn_rubric_id', 'description', 'max_points', 'position'];

    protected $casts = [
        'max_points' => 'decimal:2',
        'position' => 'integer',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'learn_rubric_id');
    }
}
