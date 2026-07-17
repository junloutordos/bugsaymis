<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSchedule extends Model
{
    protected $table = 'class_schedules';

    protected $fillable = [
        'load_assignment_id',
        'user_id',
        'subject_id',
        'section_id',
        'classroom_id',
        'school_year_id',
        'academic_term_id',
        'entry_type',
        'session_type',
        'title',
        'category',
        'day_of_week',
        'start_time',
        'end_time',
        'status',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'section_id' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function loadAssignment(): BelongsTo
    {
        return $this->belongsTo(LoadAssignment::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Duration of this slot in minutes */
    public function getDurationMinutesAttribute(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end   = \Carbon\Carbon::parse($this->end_time);
        return (int) $start->diffInMinutes($end);
    }

    /** Duration in fractional hours */
    public function getDurationHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 2);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Rows that occupy a real time slot — active AND tentative (e.g. an
     * unapplied/under-review AI-generated batch). Cancelled rows are excluded.
     * Conflict detection must use this, not active(), or it will be blind to
     * tentative schedules already sitting in the table.
     */
    public function scopeOccupying($query)
    {
        return $query->whereIn('status', ['active', 'tentative']);
    }

    /** Regular teaching sessions only (excludes non-teaching blocks). */
    public function scopeClasses($query)
    {
        return $query->where('entry_type', 'class');
    }

    /** Non-teaching blocks (consultation, research, advising, meetings, …). */
    public function scopeNonTeaching($query)
    {
        return $query->where('entry_type', 'non_teaching');
    }

    /** Independent Learning Period sessions (30-min sub-slot of a subject's period). */
    public function scopeIlp($query)
    {
        return $query->where('session_type', 'ilp');
    }

    public function scopeForTerm($query, int $termId)
    {
        return $query->where('academic_term_id', $termId);
    }

    public function scopeForFaculty($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOnDay($query, string $day)
    {
        return $query->where('day_of_week', $day);
    }

    // ── Presentation ─────────────────────────────────────────────────────────

    /**
     * Shape this row for the frontend calendar (ScheduleCalendarCard.vue) —
     * shared by the Faculty Loading schedule calendar and any other page that
     * needs to render a section's/faculty's schedule (e.g. Section Show).
     *
     * Pass $lockedFacultyIds/$cap for an editable calendar (mirrors the
     * capability rules in ClassScheduleController::scheduleCapability()); omit
     * both for a read-only render — every row then comes back is_locked=false,
     * can_edit=false, which is exactly what a display-only card needs.
     *
     * @param array<int> $lockedFacultyIds
     * @param array{level: string, faculty_ids: array<int>|null}|null $cap
     */
    public function toCalendarArray(array $lockedFacultyIds = [], ?array $cap = null): array
    {
        $isLocked = in_array((int) $this->user_id, $lockedFacultyIds, true);

        $canEdit = false;
        if ($cap !== null) {
            // Non-teaching blocks: manage, or a unit head / owner within reach.
            // Class rows: manage, or a unit head whose own faculty owns the row
            // (and never while that faculty's load is locked).
            $withinUnitReach = $this->user_id !== null
                && in_array((int) $this->user_id, $cap['faculty_ids'] ?? [], true);

            $canEdit = $this->entry_type === 'non_teaching'
                ? ($cap['level'] === 'manage' || $withinUnitReach)
                : (($cap['level'] === 'manage' || ($cap['level'] === 'unit' && $withinUnitReach)) && ! $isLocked);
        }

        return [
            'id'                 => $this->id,
            'load_assignment_id' => $this->load_assignment_id,
            'school_year_id'     => $this->school_year_id,
            'entry_type'         => $this->entry_type,
            'session_type'       => $this->session_type,
            'title'              => $this->title,
            'category'           => $this->category,
            'day_of_week'        => $this->day_of_week,
            'start_time'         => $this->start_time,
            'end_time'           => $this->end_time,
            'status'             => $this->status,
            'remarks'            => $this->remarks,
            'subject'            => $this->subject ? [
                'id'          => $this->subject->id,
                'code'        => $this->subject->code,
                'name'        => $this->subject->name,
                'is_elective' => $this->subject->grade_level === 0 || $this->subject->subject_type === 'elective',
            ] : null,
            'classroom'    => $this->classroom ? [
                'id'   => $this->classroom->id,
                'name' => $this->classroom->name,
                'code' => $this->classroom->code,
            ] : null,
            'faculty'      => $this->faculty ? [
                'id'   => $this->faculty->id,
                'name' => $this->faculty->name,
            ] : null,
            'section_id'   => $this->section_id,
            'section_name' => $this->section?->sectionname ?? ($this->section_id ? "Section {$this->section_id}" : null),
            'grade_level'  => $this->section?->levelid ?? null,
            'is_locked'    => $isLocked,
            'can_edit'     => $canEdit,
        ];
    }
}
