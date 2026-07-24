<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRecordQuarter extends Model
{
    protected $table = 'class_record_quarters';

    protected $fillable = [
        'class_record_id',
        'grading_option_id',
        'quarter',
        'is_locked',
        'locked_at',
    ];

    protected $casts = [
        'quarter' => 'integer',
        'grading_option_id' => 'integer',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function classRecord(): BelongsTo
    {
        return $this->belongsTo(ClassRecord::class);
    }

    public function gradingOption(): BelongsTo
    {
        return $this->belongsTo(GradingOption::class);
    }

    /**
     * The grading option in force for this quarter: the per-quarter override
     * if set, otherwise the record's default option. Keeps behaviour identical
     * for records/quarters created before per-quarter options existed.
     */
    public function effectiveGradingOptionId(): ?int
    {
        return $this->grading_option_id ?? $this->classRecord?->grading_option_id;
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ClassRecordAssessment::class)->orderBy('sort_order');
    }

    public function students(): HasMany
    {
        return $this->hasMany(ClassRecordStudent::class)
            ->where('is_active', true)
            ->orderBy('sequence_number');
    }
}
