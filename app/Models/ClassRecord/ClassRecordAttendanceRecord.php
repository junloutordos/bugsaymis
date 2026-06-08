<?php

namespace App\Models\ClassRecord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassRecordAttendanceRecord extends Model
{
    protected $table = 'class_record_attendance_records';

    protected $fillable = [
        'class_record_attendance_date_id',
        'class_record_student_id',
        'status',
    ];

    public function attendanceDate(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAttendanceDate::class, 'class_record_attendance_date_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(ClassRecordStudent::class, 'class_record_student_id');
    }
}
