<?php

namespace App\Models\HomeroomAttendance;

use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlagAttendanceRecord extends Model
{
    protected $table = 'homeroom_flag_attendance_records';

    protected $fillable = [
        'homeroom_flag_attendance_date_id',
        'student_id',
        'status',
        'remarks',
    ];

    protected $casts = [
        'student_id' => 'integer',
    ];

    public function attendanceDate(): BelongsTo
    {
        return $this->belongsTo(FlagAttendanceDate::class, 'homeroom_flag_attendance_date_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
