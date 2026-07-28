<?php

namespace App\Models\HomeroomAttendance;

use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionSlip extends Model
{
    protected $table = 'class_admission_slips';

    protected $fillable = [
        'student_id',
        'section_id',
        'infraction_date',
        'infraction_type',
        'excused_status',
        'reason',
        'supporting_document_path',
        'issued_by',
        'issued_at',
    ];

    protected $casts = [
        'student_id'      => 'integer',
        'section_id'      => 'integer',
        'infraction_date' => 'date:Y-m-d',
        'issued_at'       => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'admission_slip_id');
    }
}
