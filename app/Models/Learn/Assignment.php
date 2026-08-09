<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Assignment extends Model
{
    protected $table = 'learn_assignments';

    protected $fillable = ['title', 'instructions', 'submission_type', 'points_possible', 'due_at'];

    protected $casts = [
        'points_possible' => 'decimal:2',
        'due_at' => 'datetime',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }

    public function rubric(): HasOne
    {
        return $this->hasOne(Rubric::class, 'learn_assignment_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'learn_assignment_id');
    }

    /** Rubric total (when a rubric exists) takes precedence over points_possible; never both. */
    public function maxScore(): ?float
    {
        $rubric = $this->rubric;
        if ($rubric) {
            return (float) $rubric->criteria()->sum('max_points');
        }

        return $this->points_possible !== null ? (float) $this->points_possible : null;
    }

    /** The Learn course this assignment belongs to, resolved through its module item. */
    public function course(): ?Course
    {
        return $this->moduleItem?->module?->course;
    }

    public function canEdit(User $user): bool
    {
        return $this->course()?->canEdit($user) ?? false;
    }
}
