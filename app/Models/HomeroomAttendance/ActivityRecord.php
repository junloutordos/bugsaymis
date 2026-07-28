<?php

namespace App\Models\HomeroomAttendance;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRecord extends Model
{
    protected $table = 'homeroom_activity_records';

    protected $fillable = [
        'homeroom_activity_log_id',
        'student_id',
        'am_status',
        'pm_status',
        'remarks',
    ];

    protected $casts = [
        'student_id' => 'integer',
    ];

    public function activityLog(): BelongsTo
    {
        return $this->belongsTo(ActivityLog::class, 'homeroom_activity_log_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** Whole-day status this activity implies: absent only if both sessions were missed. */
    public function getWholeDayStatusAttribute(): string
    {
        return $this->am_status === 'absent' && $this->pm_status === 'absent' ? 'absent' : 'present';
    }
}
