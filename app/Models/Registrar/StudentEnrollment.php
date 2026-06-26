<?php

namespace App\Models\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEnrollment extends Model
{
    protected $table = 'student_enrollments';

    protected $fillable = [
        'student_id',
        'school_year_id',
        'section_id',
        'grade_level',
        'enrollment_type',
        'status',
        'enrollment_date',
        'notes',
        'encoded_by',
    ];

    protected $casts = [
        'student_id'      => 'integer',
        'school_year_id'  => 'integer',
        'section_id'      => 'integer',
        'grade_level'     => 'integer',
        'enrollment_date' => 'date:Y-m-d',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        // students.id is the PK; no FK constraint (MyISAM table)
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function encodedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'enrolled');
    }

    public function scopeForSchoolYear(Builder $query, int $schoolYearId): Builder
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    public function scopeForSection(Builder $query, int $sectionId): Builder
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeForGrade(Builder $query, int $grade): Builder
    {
        return $query->where('grade_level', $grade);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'enrolled';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'enrolled'        => 'Enrolled',
            'dropped'         => 'Dropped',
            'transferred_out' => 'Transferred Out',
            'on_leave'        => 'On Leave',
            'completed'       => 'Completed',
            default           => ucfirst($this->status),
        };
    }
}
