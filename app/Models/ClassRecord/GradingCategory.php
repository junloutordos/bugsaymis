<?php

namespace App\Models\ClassRecord;

use App\Models\FacultyLoading\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingCategory extends Model
{
    protected $table = 'grading_categories';

    protected $fillable = [
        'grading_option_id',
        'parent_id',
        'subject_id',
        'name',
        'code',
        'weight',
        'max_assessments',
        'sort_order',
    ];

    protected $casts = [
        'weight'          => 'float',
        'max_assessments' => 'integer',
        'sort_order'      => 'integer',
        'parent_id'       => 'integer',
        'subject_id'      => 'integer',
    ];

    public function gradingOption(): BelongsTo
    {
        return $this->belongsTo(GradingOption::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GradingCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(GradingCategory::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * The subject (PE / Health / Music) this leaf is scored under, for a
     * shared PEHM class record. Null for every non-split category — a
     * normal single-teacher class record's categories are unaffected.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** A leaf category carries the weight + assessments (has no sub-categories). */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Can $user write assessments/scores to this leaf on $classRecord?
     * Delegates to ClassRecord::canEdit() scoped to this leaf's subject_id —
     * a category with no subject_id (the normal case) is scoped to any of
     * the record's teachers, same as record-wide actions.
     */
    public function canEditOn(ClassRecord $classRecord, \App\Models\User $user): bool
    {
        return $classRecord->canEdit($user, $this->subject_id);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ClassRecordAssessment::class);
    }
}

