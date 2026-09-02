<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A class that has no slot on one adjusted day — bumped by a drag-and-drop
 * placement colliding with it. See migration docblock for the two ways
 * this is used (unplaced subject class vs. removed non-teaching block).
 */
class ClassScheduleDayAdjustmentUnplacedEntry extends Model
{
    protected $table = 'class_schedule_day_adjustment_unplaced_entries';

    protected $fillable = [
        'adjustment_id',
        'class_schedule_id',
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
