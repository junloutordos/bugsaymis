<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SectionFixedSlot;
use Illuminate\Support\Collection;

/**
 * FixedSlotService
 *
 * Seals all non-class time blocks into section_fixed_slots before any
 * subject placement runs.  The scheduler checks this table (or the
 * static helpers here) to honour Hard Constraints H4–H12:
 *
 *   H4  — no class during Flag Ceremony
 *   H5  — no class during Homeroom / Advising
 *   H6  — no class during Lunch
 *   H7  — no class during Recess
 *   H8  — no class during Consultation / Home Bound
 *   H9  — no class in the G7 Monday dead zone (08:50–09:40)
 *   H10 — no class after Wednesday Activity Start
 *   H11 — no class during Wednesday Wellness Block
 *   H12 — no regular Friday class for grades listed in FRIDAY_ILA_GRADES
 */
class FixedSlotService
{
    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * (Re-)seal all fixed slots for every active section in the given school year.
     *
     * Safe to call multiple times — existing rows for the school year are deleted
     * first, then rebuilt from SchedulingConstants.
     *
     * @param  int  $schoolYearId
     * @return int  Total rows inserted
     */
    public function sealFixedSlots(int $schoolYearId): int
    {
        // Clear previous run for this school year
        SectionFixedSlot::where('school_year_id', $schoolYearId)->delete();

        $sections = Section::where('syid', $schoolYearId)
            ->where('is_active', true)
            ->get();

        $rows    = [];
        $now     = now()->toDateTimeString();

        foreach ($sections as $section) {
            $grade      = (int) $section->levelid;
            $gradeGroup = SchedulingConstants::getGradeGroup($grade);

            foreach (SchedulingConstants::DAYS as $day) {
                $slots = self::fixedSlotsForDay($grade, $gradeGroup, $day);

                foreach ($slots as $slot) {
                    $rows[] = [
                        'section_id'    => $section->id,
                        'school_year_id'=> $schoolYearId,
                        'day_of_week'   => $day,
                        'start_time'    => $slot['start'],
                        'end_time'      => $slot['end'],
                        'slot_type'     => $slot['type'],
                        'label'         => $slot['label'] ?? null,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ];
                }
            }
        }

        // Insert in chunks to avoid packet-size issues on large schools
        foreach (array_chunk($rows, 500) as $chunk) {
            SectionFixedSlot::insert($chunk);
        }

        return count($rows);
    }

    /**
     * True when a proposed CLASS window [start, end) overlaps any fixed slot
     * for the given section and day.
     *
     * Uses a DB query — call this during constraint validation where you already
     * have a live DB connection.  For in-memory checks during generation, prefer
     * the static overlapsConstant() helper.
     */
    public function isBlocked(int $sectionId, string $day, string $start, string $end): bool
    {
        return SectionFixedSlot::where('section_id', $sectionId)
            ->where('day_of_week', $day)
            ->where('start_time', '<', $end)
            ->where('end_time',   '>', $start)
            ->exists();
    }

    /**
     * Return all fixed slots for a section on a given day (from DB).
     * Ordered by start_time for calendar rendering.
     */
    public function getSlotsForDay(int $sectionId, int $schoolYearId, string $day): Collection
    {
        return SectionFixedSlot::where('section_id', $sectionId)
            ->where('school_year_id', $schoolYearId)
            ->where('day_of_week', $day)
            ->orderBy('start_time')
            ->get();
    }

    // =========================================================================
    // Static helpers (no DB — pure constants, safe during generation loops)
    // =========================================================================

    /**
     * True when [start, end) overlaps any fixed slot for the given grade + day.
     * Derived entirely from SchedulingConstants — no DB query.
     */
    public static function overlapsConstant(int $grade, string $day, string $start, string $end): bool
    {
        $gradeGroup = SchedulingConstants::getGradeGroup($grade);
        $slots      = self::fixedSlotsForDay($grade, $gradeGroup, $day);

        foreach ($slots as $slot) {
            if ($start < $slot['end'] && $end > $slot['start']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return all fixed (non-CLASS) slots for a grade + day from constants.
     * Used both by sealFixedSlots() and by overlapsConstant().
     *
     * @return array<int, array{start:string, end:string, type:string, label:string}>
     */
    public static function fixedSlotsForDay(int $grade, string $_gradeGroup, string $day): array
    {
        return SchedulingConstants::getBlockedSlots($grade, $day);
    }
}
