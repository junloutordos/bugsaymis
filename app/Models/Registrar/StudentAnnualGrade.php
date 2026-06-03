<?php

namespace App\Models\Registrar;

use App\Models\ClassRecord\ClassRecord;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnnualGrade extends Model
{
    protected $table = 'student_annual_grades';

    protected $fillable = [
        'student_id',
        'school_year_id',
        'class_record_id',
        'subject_id',
        'subject_name',
        'q1_ge', 'q2_ge', 'q3_ge', 'q4_ge',
        'final_ge',
        'remarks',
        'is_locked',
        'locked_by',
        'locked_at',
        'computed_at',
    ];

    protected $casts = [
        'student_id'    => 'integer',
        'school_year_id'=> 'integer',
        'class_record_id'=> 'integer',
        'subject_id'    => 'integer',
        'q1_ge'         => 'decimal:3',
        'q2_ge'         => 'decimal:3',
        'q3_ge'         => 'decimal:3',
        'q4_ge'         => 'decimal:3',
        'final_ge'      => 'decimal:3',
        'is_locked'     => 'boolean',
        'locked_at'     => 'datetime',
        'computed_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function classRecord(): BelongsTo
    {
        return $this->belongsTo(ClassRecord::class, 'class_record_id');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForSchoolYear(Builder $query, int $schoolYearId): Builder
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isPassed(): bool
    {
        return $this->final_ge !== null && (float) $this->final_ge <= 3.0;
    }

    public function adjectivalLabel(): string
    {
        $ge = (float) ($this->final_ge ?? 5.0);
        if ($ge <= 1.0)  return 'Outstanding';
        if ($ge <= 1.5)  return 'Very Satisfactory';
        if ($ge <= 2.0)  return 'Satisfactory';
        if ($ge <= 2.5)  return 'Fairly Satisfactory';
        if ($ge <= 3.0)  return 'Did Not Meet Expectations';
        return 'Failed';
    }
}
