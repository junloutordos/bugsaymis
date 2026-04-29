<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a pre-sealed non-class time block for a section on a given day.
 *
 * Populated by FixedSlotService::sealFixedSlots() at the start of each
 * schedule generation run.  The scheduler must not place CLASS slots
 * over any of these windows.
 *
 * @property int    $section_id
 * @property int    $school_year_id
 * @property string $day_of_week
 * @property string $start_time    HH:MM
 * @property string $end_time      HH:MM
 * @property string $slot_type     FLAG|HOMEROOM|ADVISING|DEAD|RECESS|LUNCH|CONSULT|WELLNESS|ACTIVITY|ILA
 * @property string|null $label
 */
class SectionFixedSlot extends Model
{
    protected $table = 'section_fixed_slots';

    protected $fillable = [
        'section_id',
        'school_year_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_type',
        'label',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Duration of this block in minutes.
     */
    public function getDurationMinutesAttribute(): int
    {
        [$sh, $sm] = explode(':', $this->start_time);
        [$eh, $em] = explode(':', $this->end_time);
        return ((int) $eh * 60 + (int) $em) - ((int) $sh * 60 + (int) $sm);
    }

    /**
     * True when a proposed [classStart, classEnd) window overlaps this slot.
     * Uses half-open interval: overlap iff start < other.end AND end > other.start
     */
    public function overlaps(string $classStart, string $classEnd): bool
    {
        $s = substr($this->start_time, 0, 5);
        $e = substr($this->end_time,   0, 5);
        return $classStart < $e && $classEnd > $s;
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForSection($query, int $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeOnDay($query, string $day)
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeForSchoolYear($query, int $schoolYearId)
    {
        return $query->where('school_year_id', $schoolYearId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('slot_type', $type);
    }
}
