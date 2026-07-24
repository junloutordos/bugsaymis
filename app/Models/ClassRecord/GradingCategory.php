<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingCategory extends Model
{
    protected $table = 'grading_categories';

    protected $fillable = [
        'grading_option_id',
        'parent_id',
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

    /** A leaf category carries the weight + assessments (has no sub-categories). */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ClassRecordAssessment::class);
    }
}
