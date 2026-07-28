<?php

namespace App\Models\HomeroomAttendance;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $table = 'homeroom_attendance_records';

    protected $fillable = [
        'homeroom_attendance_date_id',
        'student_id',
        'status',
        'incomplete_uniform',
        'excused_status',
        'admission_slip_id',
        'remarks',
    ];

    protected $casts = [
        'student_id'         => 'integer',
        'incomplete_uniform' => 'boolean',
    ];

    /**
     * Whole-day statuses that count against days present when unexcused.
     * Cutting is NOT among these — it's derived per subject, never a
     * directly-selectable whole-day status (see AdmissionSlipService).
     */
    public const INFRACTION_STATUSES = ['absent', 'tardy'];

    public function attendanceDate(): BelongsTo
    {
        return $this->belongsTo(AttendanceDate::class, 'homeroom_attendance_date_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function admissionSlip(): BelongsTo
    {
        return $this->belongsTo(AdmissionSlip::class, 'admission_slip_id');
    }
}
