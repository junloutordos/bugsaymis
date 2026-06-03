<?php

namespace App\Models\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentPeriod extends Model
{
    protected $table = 'enrollment_periods';

    protected $fillable = [
        'school_year_id',
        'grade_level',
        'label',
        'open_at',
        'close_at',
        'is_manually_open',
        'is_manually_closed',
        'created_by',
    ];

    protected $casts = [
        'school_year_id'     => 'integer',
        'grade_level'        => 'integer',
        'open_at'            => 'datetime',
        'close_at'           => 'datetime',
        'is_manually_open'   => 'boolean',
        'is_manually_closed' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForSchoolYear(Builder $query, int $schoolYearId): Builder
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Returns true if enrollment is currently open.
     * Manual overrides take precedence over the date window.
     */
    public function isOpen(): bool
    {
        if ($this->is_manually_closed) {
            return false;
        }
        if ($this->is_manually_open) {
            return true;
        }

        $now = Carbon::now();
        return $now->between($this->open_at, $this->close_at);
    }

    /**
     * Check if this period applies to a given grade level.
     * A period with null grade_level applies to all grades.
     */
    public function appliesToGrade(int $grade): bool
    {
        return $this->grade_level === null || $this->grade_level === $grade;
    }
}
