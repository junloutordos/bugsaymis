<?php

namespace App\Services\HomeroomAttendance;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\FacultyLoading\AdvisoryScheduleScopeService;
use Illuminate\Support\Collection;

/**
 * Resolves which sections a user may take/manage Homeroom Attendance for, and
 * which students are on a section's roster.
 *
 * Section scope is resolved through AdvisoryScheduleScopeService (Designations
 * module, HR_ADV / HR_ACAD categories + grade-wide COORD-* coordinators) —
 * NOT the legacy Section::adviser column — mirroring the established WAT
 * pattern (see WeeklyAssessmentTrackerController). A user's sectionIds() here
 * covers both "this is my own homeroom" and "I coordinate this grade range".
 *
 * Student rosters are read from student_enrollments (status = enrolled),
 * the canonical enrollment source — NOT the legacy section_students mirror
 * table, which other modules (Guidance/Library/Health/AMS) still read but
 * which this new module has no reason to depend on.
 */
class RosterService
{
    public function __construct(private AdvisoryScheduleScopeService $advisoryScope)
    {
    }

    public function currentSchoolYear(): SchoolYear
    {
        $sy = SchoolYear::where('is_current', true)->first();
        abort_if(! $sy, 422, 'No current school year is set.');

        return $sy;
    }

    public function currentAcademicTerm(SchoolYear $sy): AcademicTerm
    {
        $term = AcademicTerm::where('school_year_id', $sy->id)->where('is_current', true)->first();
        abort_if(! $term, 422, 'No current academic term is set.');

        return $term;
    }

    /** @return array<int> */
    public function scopedSectionIds(User $user, int $academicTermId): array
    {
        return $this->advisoryScope->sectionIds($user, $academicTermId);
    }

    /**
     * homeroom-attendance.admin (CID Chief/Administrator) gets campus-wide
     * access regardless of designation — an Administrator has no HR_ADV/
     * HR_ACAD/COORD-* designation of their own, so without this bypass
     * they'd be 403'd out of a module they're supposed to have full
     * oversight of.
     */
    public function canAccessSection(User $user, int $sectionId, int $academicTermId): bool
    {
        return $user->hasPermission('homeroom-attendance.admin')
            || in_array($sectionId, $this->scopedSectionIds($user, $academicTermId), true);
    }

    /**
     * Sections (real homerooms only — Science Core/Elective synthetic
     * sections excluded, same convention as WAT) the user may manage,
     * scoped to the given school year. homeroom-attendance.admin holders
     * see every active homeroom that year; everyone else sees only their
     * designation-resolved scope (see canAccessSection()).
     */
    public function accessibleSections(User $user, int $academicTermId, int $schoolYearId): Collection
    {
        $query = Section::where('school_year_id', $schoolYearId)
            ->where('is_active', true)
            ->where('sectionname', 'not like', 'SCI-%')
            ->where('sectionname', 'not like', 'ELEC-%');

        if (! $user->hasPermission('homeroom-attendance.admin')) {
            $sectionIds = $this->scopedSectionIds($user, $academicTermId);
            if (empty($sectionIds)) {
                return collect();
            }
            $query->whereIn('id', $sectionIds);
        }

        return $query->orderBy('levelid')->orderBy('sectionname')->get(['id', 'sectionname', 'levelid']);
    }

    /**
     * Enrolled students for a section this school year, sorted the way the
     * Record on Attendance and Punctuality groups them: boys before girls,
     * then alphabetically within each group.
     *
     * @return Collection<int, Student>
     */
    public function studentsForSection(int $sectionId, int $schoolYearId): Collection
    {
        return StudentEnrollment::active()
            ->forSchoolYear($schoolYearId)
            ->forSection($sectionId)
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->values()
            ->sort(function (Student $a, Student $b) {
                return $this->sexRank($a) <=> $this->sexRank($b)
                    ?: strcmp((string) $a->lastname, (string) $b->lastname)
                    ?: strcmp((string) $a->firstname, (string) $b->firstname);
            })
            ->values();
    }

    private function sexRank(Student $student): int
    {
        return strtoupper((string) $student->sex) === 'M' ? 0 : 1;
    }
}
