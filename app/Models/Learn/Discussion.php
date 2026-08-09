<?php

namespace App\Models\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Discussion extends Model implements HasClassRecordLink
{
    protected $table = 'learn_discussions';

    protected $fillable = [
        'title', 'prompt', 'points_possible', 'class_record_assessment_id', 'pushed_at',
    ];

    protected $casts = [
        'points_possible' => 'decimal:2',
        'pushed_at' => 'datetime',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'learn_discussion_id')->orderBy('created_at');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(DiscussionGrade::class, 'learn_discussion_id');
    }

    public function classRecordAssessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }

    public function maxScore(): ?float
    {
        return $this->points_possible !== null ? (float) $this->points_possible : null;
    }

    /** The Learn course this discussion belongs to, resolved through its module item. */
    public function course(): ?Course
    {
        return $this->moduleItem?->module?->course;
    }

    public function canEdit(User $user): bool
    {
        return $this->course()?->canEdit($user) ?? false;
    }

    /** @return array<int, float> student_id => points_earned, for every graded student. */
    public function gradedStudentScores(): array
    {
        return $this->grades()
            ->whereNotNull('points_earned')
            ->pluck('points_earned', 'student_id')
            ->map(fn ($score) => (float) $score)
            ->all();
    }
}
