<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual, time-only correction applied to one specific ClassSchedule entry
 * within one adjusted-day preview — used to resolve a flagged room/faculty
 * conflict (from AdjustedClassScheduleService::assertNoGeneratedConflicts())
 * before publishing. Overrides only ever change the displayed start/end time
 * for that one entry on that one adjusted date; they never touch the
 * underlying weekly ClassSchedule row.
 */
class ClassScheduleDayAdjustmentOverride extends Model
{
    protected $table = 'class_schedule_day_adjustment_overrides';

    protected $fillable = [
        'adjustment_id',
        'class_schedule_id',
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

    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class);
    }
}
