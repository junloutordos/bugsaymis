<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    protected $table = 'learn_rubrics';

    protected $fillable = ['learn_assignment_id'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'learn_assignment_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'learn_rubric_id')->orderBy('position');
    }
}
