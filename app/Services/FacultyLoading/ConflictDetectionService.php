<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use Illuminate\Support\Collection;

/**
 * ConflictDetectionService
 *
 * Detects scheduling conflicts across three axes:
 *   A) Faculty   — same faculty, same day, overlapping time
 *   B) Room      — same classroom, same day, overlapping time
 *   C) Section   — same section, same day, overlapping time
 *
 * Time-overlap rule (standard interval intersection):
 *   Two slots A and B overlap when: (startA < endB) AND (endA > startB)
 *
 * All time comparisons are done as H:i:s strings (MySQL TIME compatible).
 */
class ConflictDetectionService
{
    // ── Core Overlap Check ───────────────────────────────────────────────────

    /**
     * Returns true if slot [startA, endA) overlaps with slot [startB, endB).
     * Touching boundaries (endA == startB) are NOT considered a conflict.
     */
    public function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        // Normalize to comparable format
        $sA = $this->normalizeTime($startA);
        $eA = $this->normalizeTime($endA);
        $sB = $this->normalizeTime($startB);
        $eB = $this->normalizeTime($endB);

        return ($sA < $eB) && ($eA > $sB);
    }

    // ── Axis A: Faculty Conflict ─────────────────────────────────────────────

    /**
     * Find existing active schedules that conflict with the proposed faculty slot.
     * @return Collection<ClassSchedule>
     */
    public function detectFacultyConflict(
        int    $facultyId,
        string $day,
        string $start,
        string $end,
        int    $termId,
        ?int   $excludeId = null
    ): Collection {
        return $this->findConflicts('user_id', $facultyId, $day, $start, $end, $termId, $excludeId);
    }

    // ── Axis B: Room Conflict ────────────────────────────────────────────────

    /**
     * Find existing active schedules that conflict with the proposed room slot.
     * @return Collection<ClassSchedule>
     */
    public function detectRoomConflict(
        int    $classroomId,
        string $day,
        string $start,
        string $end,
        int    $termId,
        ?int   $excludeId = null
    ): Collection {
        return $this->findConflicts('classroom_id', $classroomId, $day, $start, $end, $termId, $excludeId);
    }

    // ── Axis C: Section Conflict ─────────────────────────────────────────────

    /**
     * Find existing active schedules that conflict with the proposed section slot.
     * @return Collection<ClassSchedule>
     */
    public function detectSectionConflict(
        int    $sectionId,
        string $day,
        string $start,
        string $end,
        int    $termId,
        ?int   $excludeId = null
    ): Collection {
        return $this->findConflicts('section_id', $sectionId, $day, $start, $end, $termId, $excludeId);
    }

    // ── Full Conflict Check ──────────────────────────────────────────────────

    /**
     * Run all three conflict axes at once.
     *
     * @param array $data {
     *   faculty_id:    int,
     *   classroom_id:  int,
     *   section_id:    int,
     *   day_of_week:   string,
     *   start_time:    string,
     *   end_time:      string,
     *   academic_term_id: int,
     * }
     * @param int|null $excludeId  ClassSchedule id to exclude (for updates)
     *
     * @return array {
     *   has_conflicts: bool,
     *   conflicts: [
     *     faculty:  Collection,
     *     room:     Collection,
     *     section:  Collection,
     *   ],
     *   messages: string[],
     * }
     */
    public function detectAllConflicts(array $data, ?int $excludeId = null): array
    {
        $day    = $data['day_of_week'];
        $start  = $data['start_time'];
        $end    = $data['end_time'];
        $termId = $data['academic_term_id'];

        $facultyConflicts = $this->detectFacultyConflict(
            $data['faculty_id'], $day, $start, $end, $termId, $excludeId
        );

        $roomConflicts = $this->detectRoomConflict(
            $data['classroom_id'], $day, $start, $end, $termId, $excludeId
        );

        $sectionConflicts = $this->detectSectionConflict(
            $data['section_id'], $day, $start, $end, $termId, $excludeId
        );

        $messages = [];

        foreach ($facultyConflicts as $c) {
            $messages[] = "Faculty conflict: faculty already has '{$c->subject?->name}' on {$c->day_of_week} {$c->start_time}–{$c->end_time}.";
        }
        foreach ($roomConflicts as $c) {
            $messages[] = "Room conflict: {$c->classroom?->name} is already booked for '{$c->subject?->name}' on {$c->day_of_week} {$c->start_time}–{$c->end_time}.";
        }
        foreach ($sectionConflicts as $c) {
            $messages[] = "Section conflict: section already has '{$c->subject?->name}' on {$c->day_of_week} {$c->start_time}–{$c->end_time}.";
        }

        return [
            'has_conflicts' => $facultyConflicts->isNotEmpty()
                            || $roomConflicts->isNotEmpty()
                            || $sectionConflicts->isNotEmpty(),
            'conflicts' => [
                'faculty'  => $facultyConflicts,
                'room'     => $roomConflicts,
                'section'  => $sectionConflicts,
            ],
            'messages' => $messages,
        ];
    }

    // ── Teaching Hours Per Day ───────────────────────────────────────────────

    /**
     * Sum the teaching hours a faculty already has on a given day.
     * Optionally excludes a schedule row (for updates).
     */
    public function getDailyTeachingHours(
        int    $facultyId,
        string $day,
        int    $termId,
        ?int   $excludeId = null
    ): float {
        $query = ClassSchedule::occupying()
            ->where('user_id', $facultyId)
            ->where('day_of_week', $day)
            ->where('academic_term_id', $termId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $schedules = $query->get();

        $totalMinutes = $schedules->sum(fn ($s) =>
            $this->minutesBetween($s->start_time, $s->end_time)
        );

        return round($totalMinutes / 60, 2);
    }

    /**
     * Calculate the projected daily hours AFTER adding a new slot.
     */
    public function getProjectedDailyHours(
        int    $facultyId,
        string $day,
        string $newStart,
        string $newEnd,
        int    $termId,
        ?int   $excludeId = null
    ): float {
        $existing = $this->getDailyTeachingHours($facultyId, $day, $termId, $excludeId);
        $newSlot  = $this->minutesBetween($newStart, $newEnd) / 60;
        return round($existing + $newSlot, 2);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Generic overlap finder for a single conflict axis.
     * Uses the DB time-overlap condition for efficiency on large datasets.
     */
    private function findConflicts(
        string $column,
        int    $value,
        string $day,
        string $start,
        string $end,
        int    $termId,
        ?int   $excludeId
    ): Collection {
        $start = $this->normalizeTime($start);
        $end   = $this->normalizeTime($end);

        $query = ClassSchedule::with(['subject', 'classroom', 'faculty'])
            ->occupying()
            ->where($column, $value)
            ->where('day_of_week', $day)
            ->where('academic_term_id', $termId)
            // Time overlap: (startA < endB) AND (endA > startB)
            ->where('start_time', '<', $end)
            ->where('end_time',   '>', $start);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * Convert a time string to H:i:s for consistent comparisons.
     * Accepts "H:i", "H:i:s", or Carbon-parseable strings.
     */
    private function normalizeTime(string $time): string
    {
        $parts = explode(':', $time);
        $h = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
        $m = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);
        $s = str_pad($parts[2] ?? '00', 2, '0', STR_PAD_LEFT);
        return "{$h}:{$m}:{$s}";
    }

    /**
     * Minutes between two time strings.
     */
    private function minutesBetween(string $start, string $end): int
    {
        $sMin = $this->timeToMinutes($start);
        $eMin = $this->timeToMinutes($end);
        return max(0, $eMin - $sMin);
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        return ((int) $h * 60) + (int) $m;
    }
}
