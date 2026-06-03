<?php

namespace App\Services\Registrar;

use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Handles year-end promotion processing:
 * 1. Preview: compute GWA/standing for all enrolled students without saving
 * 2. Confirm: persist standings and create next-year enrollment stubs
 */
class StudentPromotionService
{
    public function __construct(
        private readonly StudentTranscriptService $transcriptService,
    ) {}

    // ── Preview (dry run) ─────────────────────────────────────────────────────

    /**
     * Returns a preview of promotion results for all enrolled students.
     * Does NOT write to the database.
     */
    public function preview(int $schoolYearId): array
    {
        $enrollments = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled')
            ->with('section:id,sectionname,levelid')
            ->get();

        // Pre-load existing annual grades for these students
        $studentIds = $enrollments->pluck('student_id');
        $gradesByStudent = StudentAnnualGrade::where('school_year_id', $schoolYearId)
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('final_ge')
            ->get()
            ->groupBy('student_id');

        $existingStandings = StudentAcademicStanding::where('school_year_id', $schoolYearId)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $rows = [];
        foreach ($enrollments as $enrollment) {
            $sid    = $enrollment->student_id;
            $grades = $gradesByStudent->get($sid, collect());

            $subjectCount  = $grades->count();
            $failedCount   = $grades->filter(fn ($g) => (float) $g->final_ge > 3.0)->count();
            $gwa           = $subjectCount > 0
                ? round($grades->avg(fn ($g) => (float) $g->final_ge), 3)
                : null;

            $existing = $existingStandings->get($sid);

            $rows[] = [
                'student_id'           => $sid,
                'grade_level'          => $enrollment->grade_level,
                'section_name'         => $enrollment->section?->sectionname,
                'section_id'           => $enrollment->section_id,
                'subject_count'        => $subjectCount,
                'failed_subject_count' => $failedCount,
                'gwa'                  => $gwa,
                'gwa_display'          => $gwa ? number_format($gwa, 3) : '—',
                'honors'               => $existing?->honors ?? '—',
                'standing'             => $existing?->standing ?? ($failedCount >= 3 ? 'Retained' : 'Promoted'),
                'grades_computed'      => $subjectCount > 0,
            ];
        }

        return $rows;
    }

    // ── Confirm promotion ─────────────────────────────────────────────────────

    /**
     * Finalise standings and create enrollment stubs for the next school year.
     * Overrides provided in $overrides: [studentId => 'Promoted'|'Retained'|'Excluded'|...]
     */
    public function confirm(int $schoolYearId, int $nextSchoolYearId, array $overrides = []): array
    {
        $enrollments = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled')
            ->with('section:id,sectionname,levelid')
            ->get();

        $studentIds = $enrollments->pluck('student_id');
        $gradesByStudent = StudentAnnualGrade::where('school_year_id', $schoolYearId)
            ->whereIn('student_id', $studentIds)
            ->whereNotNull('final_ge')
            ->get()
            ->groupBy('student_id');

        $promoted = 0;
        $retained = 0;
        $excluded = 0;

        DB::transaction(function () use (
            $enrollments, $gradesByStudent, $overrides, $schoolYearId, $nextSchoolYearId,
            &$promoted, &$retained, &$excluded
        ) {
            $nextSY = SchoolYear::find($nextSchoolYearId);

            foreach ($enrollments as $enrollment) {
                $sid    = $enrollment->student_id;
                $grades = $gradesByStudent->get($sid, collect());

                $subjectCount = $grades->count();
                $failedCount  = $grades->filter(fn ($g) => (float) $g->final_ge > 3.0)->count();
                $gwa          = $subjectCount > 0
                    ? round($grades->avg(fn ($g) => (float) $g->final_ge), 3)
                    : null;

                // Recompute standing (or use override)
                $standing = $overrides[$sid] ?? null;
                if (! $standing) {
                    $this->transcriptService->computeStanding(
                        $sid,
                        $schoolYearId,
                        $enrollment->grade_level,
                        Auth::id()
                    );
                    $standing = StudentAcademicStanding::where('student_id', $sid)
                        ->where('school_year_id', $schoolYearId)
                        ->value('standing') ?? 'Promoted';
                } else {
                    StudentAcademicStanding::updateOrCreate(
                        ['student_id' => $sid, 'school_year_id' => $schoolYearId],
                        [
                            'standing'     => $standing,
                            'processed_by' => Auth::id(),
                            'processed_at' => Carbon::now(),
                        ]
                    );
                }

                // Mark current enrollment as completed
                $enrollment->update(['status' => 'completed']);

                // Only promoted Grade 12 students are "graduated"
                $isGrade12  = $enrollment->grade_level === 12;
                $isPromoted = $standing === 'Promoted';

                if ($standing === 'Excluded') {
                    $excluded++;
                    continue;
                }

                if ($isGrade12 && $isPromoted) {
                    StudentAcademicStanding::where('student_id', $sid)
                        ->where('school_year_id', $schoolYearId)
                        ->update(['standing' => 'Graduated']);
                    $promoted++;
                    continue;
                }

                // Create next-year enrollment stub (section TBD)
                $nextGrade = $isPromoted ? min(12, $enrollment->grade_level + 1) : $enrollment->grade_level;

                $alreadyExists = StudentEnrollment::where('student_id', $sid)
                    ->where('school_year_id', $nextSchoolYearId)
                    ->exists();

                if (! $alreadyExists) {
                    StudentEnrollment::create([
                        'student_id'      => $sid,
                        'school_year_id'  => $nextSchoolYearId,
                        'section_id'      => $enrollment->section_id, // keep same; registrar can reassign
                        'grade_level'     => $nextGrade,
                        'enrollment_type' => 'returning',
                        'status'          => 'enrolled',
                        'enrollment_date' => Carbon::now()->toDateString(),
                        'encoded_by'      => Auth::id(),
                        'notes'           => $standing === 'Retained'
                            ? "Retained from S.Y. {$schoolYearId}"
                            : null,
                    ]);
                }

                $isPromoted ? $promoted++ : $retained++;
            }
        });

        return compact('promoted', 'retained', 'excluded');
    }
}
