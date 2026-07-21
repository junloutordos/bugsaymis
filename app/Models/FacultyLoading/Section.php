<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\FacultyLoading\Classroom;

/**
 * Represents a student section/class at PSHS.
 *
 * Maps to the existing `sections` table (created in migration
 * 2026_01_21_000200). The legacy schema uses non-standard column
 * names; the Faculty Loading module adds section_code, strand,
 * capacity, and is_active via migration 2026_03_27_000011.
 *
 * Legacy → FL column mapping:
 *   levelid     → grade level (integer)
 *   sectionname → section name (string)
 *   syid        → school year ID (raw int, no FK)
 *   adviser     → adviser user ID (raw int)
 */
class Section extends Model
{
    use HasFactory;

    protected $table    = 'sections';
    protected $keyType  = 'int';       // INT primary key (not BigInt)
    public    $timestamps = false;     // legacy table has no timestamps

    protected $fillable = [
        'levelid',
        'sectionname',
        'section_code',
        'strand',
        'capacity',
        'classroom_id',
        'recess_start',
        'recess_end',
        'lunch_start',
        'lunch_end',
        'lunch_start_mon',
        'lunch_end_mon',
        'lunch_start_tue',
        'lunch_end_tue',
        'lunch_start_wed',
        'lunch_end_wed',
        'lunch_start_thu',
        'lunch_end_thu',
        'lunch_start_fri',
        'lunch_end_fri',
        'recess_start_mon',
        'recess_end_mon',
        'recess_start_tue',
        'recess_end_tue',
        'recess_start_wed',
        'recess_end_wed',
        'recess_start_thu',
        'recess_end_thu',
        'recess_start_fri',
        'recess_end_fri',
        'afternoon_break_start',
        'afternoon_break_end',
        'adviser',
        'syid',
        'is_active',
        'permission',
        'asstadviser',
        'school_year_id',
    ];

    /**
     * Day name → [start column, end column] for this section's own per-day
     * lunch override. Every layer that needs to resolve a section+day to its
     * override columns (validation, the scheduling grid, the calendar
     * controller, the popover endpoint) goes through this single map so they
     * can never drift out of sync with each other.
     */
    public const LUNCH_OVERRIDE_COLUMNS = [
        'Monday'    => ['lunch_start_mon', 'lunch_end_mon'],
        'Tuesday'   => ['lunch_start_tue', 'lunch_end_tue'],
        'Wednesday' => ['lunch_start_wed', 'lunch_end_wed'],
        'Thursday'  => ['lunch_start_thu', 'lunch_end_thu'],
        'Friday'    => ['lunch_start_fri', 'lunch_end_fri'],
    ];

    /**
     * Day name → [start column, end column] for this section's own per-day
     * recess override. Mirrors LUNCH_OVERRIDE_COLUMNS — same single-map
     * pattern so validation, the scheduling grid, the calendar controller,
     * and the popover endpoint can never drift out of sync with each other.
     */
    public const RECESS_OVERRIDE_COLUMNS = [
        'Monday'    => ['recess_start_mon', 'recess_end_mon'],
        'Tuesday'   => ['recess_start_tue', 'recess_end_tue'],
        'Wednesday' => ['recess_start_wed', 'recess_end_wed'],
        'Thursday'  => ['recess_start_thu', 'recess_end_thu'],
        'Friday'    => ['recess_start_fri', 'recess_end_fri'],
    ];

    protected $casts = [
        'levelid'        => 'integer',
        'syid'           => 'integer',
        'school_year_id' => 'integer',
        'capacity'       => 'integer',
        'classroom_id'   => 'integer',
        'is_active'      => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    /** School year (via legacy syid column). */
    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'syid');
    }

    /** School year (via new FL FK column). */
    public function flSchoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    /** Section adviser (via legacy adviser column). */
    public function adviserUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser');
    }

    /** Home classroom for this section (used by the schedule generator). */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'section_id');
    }

    public function loadAssignments(): HasMany
    {
        return $this->hasMany(LoadAssignment::class, 'section_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGrade($query, int $grade)
    {
        return $query->where('levelid', $grade);
    }

    public function scopeForSchoolYear($query, int $schoolYearId)
    {
        return $query->where('syid', $schoolYearId);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    /** Canonical grade level (maps legacy levelid). */
    public function getGradeLevelAttribute(): int
    {
        return (int) $this->levelid;
    }

    /** Canonical name (maps legacy sectionname). */
    public function getNameAttribute(): string
    {
        return (string) $this->sectionname;
    }

    /** e.g., "Grade 7 — Newton" */
    public function getFullLabelAttribute(): string
    {
        return "Grade {$this->grade_level} — {$this->sectionname}";
    }

    /**
     * This section's own lunch override for the given weekday, or null if
     * unset (falls back to the section's regular lunch_start/lunch_end, or
     * ultimately the grade default) — see LUNCH_OVERRIDE_COLUMNS.
     *
     * @return array{start:string,end:string}|null
     */
    public function lunchOverrideFor(string $day): ?array
    {
        [$startCol, $endCol] = self::LUNCH_OVERRIDE_COLUMNS[$day] ?? [null, null];
        if (! $startCol || ! $this->$startCol || ! $this->$endCol) {
            return null;
        }

        return ['start' => $this->$startCol, 'end' => $this->$endCol];
    }

    /**
     * This section's own recess override for the given weekday, or null if
     * unset (falls back to the grade default) — see RECESS_OVERRIDE_COLUMNS.
     * When set, this single window replaces every RECESS block the grade
     * default has for that day (a grade can have more than one).
     *
     * @return array{start:string,end:string}|null
     */
    public function recessOverrideFor(string $day): ?array
    {
        [$startCol, $endCol] = self::RECESS_OVERRIDE_COLUMNS[$day] ?? [null, null];
        if (! $startCol || ! $this->$startCol || ! $this->$endCol) {
            return null;
        }

        return ['start' => $this->$startCol, 'end' => $this->$endCol];
    }
}
