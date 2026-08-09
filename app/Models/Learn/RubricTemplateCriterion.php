<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricTemplateCriterion extends Model
{
    protected $table = 'learn_rubric_template_criteria';

    protected $fillable = ['learn_rubric_template_id', 'description', 'max_points', 'position'];

    protected $casts = [
        'max_points' => 'decimal:2',
        'position' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RubricTemplate::class, 'learn_rubric_template_id');
    }
}
