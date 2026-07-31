<?php

namespace App\Models\ClassRecord;

use App\Models\Consultation;
use App\Models\HomeroomAttendance\AdmissionSlip;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecordAttendanceRecord extends Model
{
    protected $table = 'class_record_attendance_records';

    // uniform_status is legacy — no longer written by app code (the
    // Homeroom Adviser checks uniform once per day now, not per subject).
    // Column stays for now; dropping it is a separate later migration.
    //
    // incomplete_uniform is the reintroduced per-subject flag — independent
    // of `status` (a teacher can flag IU on a Present day too), synced
    // one-directionally into Homeroom's own `incomplete_uniform` column via
    // SubjectAttendanceSyncService.
    protected $fillable = [
        'class_record_attendance_date_id',
        'class_record_student_id',
        'status',
        'excused_status',
        'admission_slip_id',
        'clinic_consultation_id',
        'excused_by',
        'excused_at',
        'synced_from_homeroom',
        'incomplete_uniform',
        'remarks',
    ];

    protected $casts = [
        'synced_from_homeroom' => 'boolean',
        'incomplete_uniform' => 'boolean',
        'excused_at' => 'datetime',
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

    public function clinicConsultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class, 'clinic_consultation_id');
    }

    public function excusedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excused_by');
    }
}
