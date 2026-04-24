<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the per-day official working time for a faculty member.
 *
 * Used by the scheduler to enforce H3 — no class outside official time.
 * Defaults to the early shift (07:30–16:30) when no record exists.
 */
class TeacherOfficialTime extends Model
{
    protected $table = 'teacher_official_times';

    protected $fillable = [
        'user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'notes',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return the shift label based on the start time.
     *   07:30 → 'early'
     *   08:00 → 'late'
     */
    public function getShiftAttribute(): string
    {
        return str_starts_with($this->start_time, '07') ? 'early' : 'late';
    }

    /**
     * True when a proposed [classStart, classEnd) window fits within
     * this teacher's official time on this day.
     */
    public function allows(string $classStart, string $classEnd): bool
    {
        return $classStart >= substr($this->start_time, 0, 5)
            && $classEnd   <= substr($this->end_time,   0, 5);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForTeacher($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOnDay($query, string $day)
    {
        return $query->where('day_of_week', $day);
    }
}
