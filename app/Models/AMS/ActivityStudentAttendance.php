<?php

namespace App\Models\AMS;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityStudentAttendance extends Model
{
    protected $table = 'ams_activity_student_attendance';

    protected $fillable = [
        'activity_id',
        'participant_id',
        'attended',
        'hours_attended',
        'certificate_path',
    ];

    protected $casts = [
        'hours_attended' => 'decimal:2',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
