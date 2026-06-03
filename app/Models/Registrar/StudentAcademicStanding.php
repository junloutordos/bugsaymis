<?php

namespace App\Models\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAcademicStanding extends Model
{
    protected $table = 'student_academic_standing';

    protected $fillable = [
        'student_id',
        'school_year_id',
        'gwa',
        'subject_count',
        'failed_subject_count',
        'honors',
        'standing',
        'notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'student_id'           => 'integer',
        'school_year_id'       => 'integer',
        'gwa'                  => 'decimal:3',
        'subject_count'        => 'integer',
        'failed_subject_count' => 'integer',
        'processed_at'         => 'datetime',
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

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForSchoolYear(Builder $query, int $schoolYearId): Builder
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    public function scopePromoted(Builder $query): Builder
    {
        return $query->where('standing', 'Promoted');
    }

    public function scopeRetained(Builder $query): Builder
    {
        return $query->where('standing', 'Retained');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function standingBadgeColor(): string
    {
        return match ($this->standing) {
            'Promoted'    => 'bg-green-100 text-green-700',
            'Graduated'   => 'bg-indigo-100 text-indigo-700',
            'Retained'    => 'bg-amber-100 text-amber-700',
            'Excluded'    => 'bg-red-100 text-red-700',
            'Transferred' => 'bg-blue-100 text-blue-700',
            'Dropped'     => 'bg-slate-100 text-slate-600',
            default       => 'bg-slate-100 text-slate-600',
        };
    }
}
