<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual, time-only correction applied to one Recess or White Space band
 * within one adjusted-day preview, scoped to one section — mirrors
 * ClassScheduleDayAdjustmentOverride but for a bell-schedule band instead
 * of a real ClassSchedule row. Never touches the underlying weekly
 * schedule or bell-schedule configuration.
 */
class ClassScheduleDayAdjustmentBandOverride extends Model
{
    protected $table = 'class_schedule_day_adjustment_band_overrides';

    protected $fillable = [
        'adjustment_id',
        'section_id',
        'band_type',
        'override_start_time',
        'override_end_time',
    ];

    protected $casts = [
        'override_start_time' => 'string',
        'override_end_time' => 'string',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(ClassScheduleDayAdjustment::class, 'adjustment_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
