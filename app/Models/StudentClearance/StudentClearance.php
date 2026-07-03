<?php

namespace App\Models\StudentClearance;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentClearance extends Model
{
    protected $fillable = [
        'student_clearance_period_id',
        'school_year_id',
        'student_id',
        'pisaysystem_id',
        'student_enrollment_id',
        'section_id',
        'grade_level',
        'adviser_id',
        'status',
        'submitted_at',
        'adviser_reviewed_by',
        'adviser_reviewed_at',
        'registrar_received_by',
        'registrar_received_at',
        'finalized_at',
    ];

    protected $casts = [
        'student_clearance_period_id' => 'integer',
        'school_year_id'              => 'integer',
        'student_id'                  => 'integer',
        'student_enrollment_id'       => 'integer',
        'section_id'                  => 'integer',
        'grade_level'                 => 'integer',
        'adviser_id'                  => 'integer',
        'submitted_at'                => 'datetime',
        'adviser_reviewed_at'         => 'datetime',
        'registrar_received_at'       => 'datetime',
        'finalized_at'                => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(StudentClearancePeriod::class, 'student_clearance_period_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentClearanceItem::class)->orderBy('sort_order')->orderBy('requirement_label');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StudentClearanceActivityLog::class);
    }
}
