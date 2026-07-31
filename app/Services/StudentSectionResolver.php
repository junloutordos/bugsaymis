<?php

namespace App\Services;

use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a student's current grade level + section for display across the
 * clinic, guidance, library and discipline modules.
 *
 * The authoritative source is the Registrar's `student_enrollments` table for
 * the CURRENT school year. The legacy `section_students` mirror is only used as
 * a fallback for students with no current-SY enrollment row (e.g. alumni, or
 * data that predates the enrollment module).
 *
 * Previously every consumer read `section_students` ordered by `id` descending,
 * which is not school-year aware — it silently showed last year's section
 * whenever the new-SY assignment had not been mirrored yet.
 */
class StudentSectionResolver
{
    /** Memoized current school year id (per request). */
    private static int|false|null $currentSchoolYearId = null;

    /**
     * Return a row exposing `->levelid` (grade level) and `->sectionid` for a
     * student, shaped to match the legacy `section_students` row so callers can
     * read `->levelid` / `->sectionid` unchanged. Returns null when nothing is
     * known about the student.
     */
    public function latestForStudent(int $studentId): ?object
    {
        $currentSyId = $this->currentSchoolYearId();

        if ($currentSyId) {
            $enrollment = DB::table('student_enrollments')
                ->where('student_id', $studentId)
                ->where('school_year_id', $currentSyId)
                ->whereIn('status', ['enrolled', 'on_leave'])
                ->orderByDesc('id')
                ->first();

            if ($enrollment) {
                return (object) [
                    'studentid' => $studentId,
                    'levelid'   => $enrollment->grade_level,
                    'sectionid' => $enrollment->section_id,
                ];
            }
        }

        // Legacy fallback: newest mirror row (no current-SY enrollment found).
        return DB::table('section_students')
            ->where('studentid', $studentId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve a formatted "Grade X SectionName" string (either part may be
     * empty). Convenience for callers that only need a display string.
     */
    public function gradeSectionString(int $studentId): string
    {
        $row = $this->latestForStudent($studentId);

        if (! $row) {
            return '';
        }

        $grade = ! empty($row->levelid) ? 'Grade ' . $row->levelid : '';
        $sectionName = '';

        if (! empty($row->sectionid)) {
            $section = DB::table('sections')->where('id', $row->sectionid)->first();
            if ($section) {
                foreach (['sectionname', 'section_name', 'name', 'section'] as $col) {
                    if (isset($section->$col) && $section->$col !== null && $section->$col !== '') {
                        $sectionName = $section->$col;
                        break;
                    }
                }
            }
        }

        return trim($grade . ($sectionName ? ' ' . $sectionName : ''));
    }

    private function currentSchoolYearId(): ?int
    {
        if (self::$currentSchoolYearId === null) {
            self::$currentSchoolYearId = SchoolYear::where('is_current', true)->value('id') ?? false;
        }

        return self::$currentSchoolYearId ?: null;
    }
}
