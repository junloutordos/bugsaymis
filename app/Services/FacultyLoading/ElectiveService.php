<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Section;

/**
 * Elective subjects behave like Science Core (see ScienceCoreService): a
 * grade-wide group of otherwise-unrelated subjects taught at the same
 * shared day/period(s), cutting across homerooms, so a student attends
 * whichever elective matches their choice regardless of which homeroom
 * they belong to.
 *
 * Used only by the Adjusted Day Calendar (AdjustedClassScheduleService),
 * where the band must reflect what actually happens to be scheduled that
 * grade/day. SchedulingConstants::getElectiveWindows() — a hardcoded
 * bell-schedule lookup independent of real data — remains untouched for
 * its other callers (regular weekly schedule display, Class Record WAT
 * rules): switching those to real-data sourcing was explicitly out of
 * scope for this fix.
 */
class ElectiveService
{
    /** Synthetic section name prefix, mirroring ScienceCoreService::SECTION_PREFIX. */
    public const SECTION_PREFIX = 'ELEC-';

    /**
     * The Elective display window(s) for a grade on a day, computed from
     * where the real ELEC-* sessions actually landed in class_schedules.
     * Unlike Science Core (a single group that always shares one slot),
     * distinct elective subjects can run at separate, non-contiguous
     * periods the same day — concurrent sessions at the same slot collapse
     * into one window; sessions elsewhere in the day produce another.
     *
     * @return array<int,array{start:string,end:string,label:string}>
     */
    public function getElectiveWindows(int $schoolYearId, int $termId, int $grade, string $day): array
    {
        $sectionIds = Section::where('school_year_id', $schoolYearId)
            ->where('levelid', $grade)
            ->where('sectionname', 'like', self::SECTION_PREFIX.'%')
            ->pluck('id');

        if ($sectionIds->isEmpty()) {
            return [];
        }

        $rows = ClassSchedule::whereIn('section_id', $sectionIds)
            ->where('academic_term_id', $termId)
            ->where('day_of_week', $day)
            ->occupying()
            ->classes()
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        if ($rows->isEmpty()) {
            return [];
        }

        $windows = [];
        foreach ($rows as $row) {
            $start = substr((string) $row->start_time, 0, 5);
            $end = substr((string) $row->end_time, 0, 5);

            $lastIdx = count($windows) - 1;
            if ($lastIdx >= 0 && $windows[$lastIdx]['end'] >= $start) {
                $windows[$lastIdx]['end'] = max($windows[$lastIdx]['end'], $end);
            } else {
                $windows[] = ['start' => $start, 'end' => $end, 'label' => 'Electives'];
            }
        }

        return $windows;
    }
}
