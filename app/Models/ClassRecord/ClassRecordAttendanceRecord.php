<?php

namespace App\Models\ClassRecord;

use App\Models\HomeroomAttendance\AdmissionSlip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecordAttendanceRecord extends Model
{
    protected $table = 'class_record_attendance_records';

    // uniform_status is legacy — no longer written by app code (the
    // Homeroom Adviser checks uniform once per day now, not per subject).
    // Column stays for now; dropping it is a separate later migration.
    protected $fillable = [
        'class_record_attendance_date_id',
        'class_record_student_id',
        'status',
        'excused_status',
        'admission_slip_id',
        'synced_from_homeroom',
    ];

    protected $casts = [
        'synced_from_homeroom' => 'boolean',
    ];

    public function attendanceDate(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAttendanceDate::class, 'class_record_attendance_date_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(ClassRecordStudent::class, 'class_record_student_id');
    }

    public function admissionSlip(): BelongsTo
    {
        return $this->belongsTo(AdmissionSlip::class, 'admission_slip_id');
    }
}
