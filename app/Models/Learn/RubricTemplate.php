<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricTemplate extends Model
{
    protected $table = 'learn_rubric_templates';

    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricTemplateCriterion::class, 'learn_rubric_template_id')->orderBy('position');
    }
}
