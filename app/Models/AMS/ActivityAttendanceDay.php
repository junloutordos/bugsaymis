<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAttendanceDay extends Model
{
    protected $table = 'ams_activity_attendance_days';

    protected $fillable = [
        'activity_id',
        'participant_type',
        'participant_id',
        'date',
        'attended',
        'hours_attended',
    ];

    protected $casts = [
        'hours_attended' => 'decimal:2',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
