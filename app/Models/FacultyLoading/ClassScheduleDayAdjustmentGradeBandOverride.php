<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual, time-only correction applied to the ELECTIVE or SCIENCE_CORE
 * band within one adjusted-day preview, scoped to a whole grade level —
 * mirrors ClassScheduleDayAdjustmentBandOverride but keyed by grade_level
 * instead of section_id, since these bands are cross-homeroom groups that
 * must show identically across every section in the grade.
 */
class ClassScheduleDayAdjustmentGradeBandOverride extends Model
{
    protected $table = 'class_schedule_day_adjustment_grade_band_overrides';

    protected $fillable = [
        'adjustment_id',
        'grade_level',
        'band_type',
        'override_start_time',
        'override_end_time',
    ];

    protected $casts = [
        'grade_level' => 'integer',
        'override_start_time' => 'string',
        'override_end_time' => 'string',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(ClassScheduleDayAdjustment::class, 'adjustment_id');
    }
}
