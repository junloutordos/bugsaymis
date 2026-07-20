<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use Illuminate\Support\Collection;

/**
 * Science Core subjects (e.g. "Biology 3 Level 2", "Chemistry 3 Level 2",
 * "Physics 3 Level 2" for Grade 11) behave like electives in scheduling: a
 * grade-wide group of otherwise-unrelated subjects that must all be taught
 * at the exact same shared day/period, cutting across homerooms, so a
 * student attends whichever one matches their track regardless of which
 * homeroom they belong to.
 *
 * Unlike generic electives (a single fixed bell-schedule period, e.g. every
 * Tue-Fri 13:50), Science Core has no pre-reserved window — it reuses
 * whatever day/period the DeterministicSchedulingService placement pre-pass
 * finds free across the whole group. That means, unlike
 * SchedulingConstants::getElectiveWindows() (a pure bell-schedule lookup),
 * the display window here has to be read back from wherever the sessions
 * actually landed in class_schedules.
 *
 * subjects.subject_type = 'science_core' is the single source of truth for
 * which subjects belong to a grade's group — no hardcoded subject list.
 */
class ScienceCoreService
{
    /** Synthetic section name prefix, mirroring the existing "ELEC-" convention. */
    public const SECTION_PREFIX = 'SCI-';

    /** Science Core subjects for a grade in a school year, ordered by code. */
    public function subjectsForGrade(int $schoolYearId, int $grade): Collection
    {
        return Subject::where('school_year_id', $schoolYearId)
            ->where('grade_level', $grade)
            ->where('subject_type', 'science_core')
            ->orderBy('code')
            ->get();
    }

    /** Grades (7-12) that have at least one Science Core subject this school year. */
    public function gradesWithScienceCore(int $schoolYearId): array
    {
        return Subject::where('school_year_id', $schoolYearId)
            ->where('subject_type', 'science_core')
            ->whereBetween('grade_level', [7, 12])
            ->distinct()
            ->pluck('grade_level')
            ->map(fn ($g) => (int) $g)
            ->values()
            ->all();
    }

    /** True if $section is one of the synthetic per-class Science Core sections. */
    public function isScienceCoreSection(Section $section): bool
    {
        return str_starts_with((string) $section->sectionname, self::SECTION_PREFIX);
    }

    /**
     * The Science Core display window(s) for a grade on a day — analogous to
     * SchedulingConstants::getElectiveWindows(), but computed from where the
     * group's sessions actually landed (occupying rows on its synthetic
     * sections) rather than a fixed bell-schedule label, since there's no
     * pre-reserved period to read from statically.
     *
     * @return array<int,array{start:string,end:string,label:string}>
     */
    public function getScienceCoreWindows(int $schoolYearId, int $termId, int $grade, string $day): array
    {
        $sectionIds = Section::where('school_year_id', $schoolYearId)
            ->where('levelid', $grade)
            ->where('sectionname', 'like', self::SECTION_PREFIX . '%')
            ->pluck('id');

        if ($sectionIds->isEmpty()) {
            return [];
        }

        $rows = ClassSchedule::whereIn('section_id', $sectionIds)
            ->where('academic_term_id', $termId)
            ->where('day_of_week', $day)
            ->occupying()
            ->classes()
            ->get(['start_time', 'end_time']);

        if ($rows->isEmpty()) {
            return [];
        }

        // The pre-pass places every class in the group at the identical shared
        // slot, so in the normal case this collapses to a single window; MIN/MAX
        // is a defensive merge in case of partial/edge-case data.
        $start = $rows->min('start_time');
        $end   = $rows->max('end_time');

        return [['start' => substr((string) $start, 0, 5), 'end' => substr((string) $end, 0, 5), 'label' => 'Science Core']];
    }
}
