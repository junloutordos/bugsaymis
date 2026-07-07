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
}
