<?php

namespace App\Services\Registrar;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\StanineLookup;
use App\Models\Registrar\RetentionPolicy;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Services\ClassRecord\GradeComputationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Computes and stores annual grade snapshots for a student from the class record gradebooks.
 *
 * Grade computation uses the existing GradeComputationService and stanine lookup table.
 * Running grades per quarter: Q1 = Q1GE; Q2–Q4 = floor((currentQGE × 2/3) + (prevRunning × 1/3))
 * Final grade per subject = average of Q1–Q4 running GEs.
 * GWA = simple average of all final GEs across subjects.
 */
class StudentTranscriptService
{
    public function __construct(
        private readonly GradeComputationService $grader,
    ) {}

    // ── Compute & persist grades for one student ──────────────────────────────

    /**
     * Compute grades for every class record the student is enrolled in for the given school year
     * and upsert into student_annual_grades.
     *
     * Returns the list of computed grade rows.
     */
    public function computeAndSave(int $studentId, int $schoolYearId): array
    {
        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->first();

        if (! $enrollment) {
            return [];
        }

        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        // Roster membership is authoritative. This includes regular sections,
        // cross-section electives, and Grade 11/12 Science Core subjects.
        $classRecords = ClassRecord::with('gradingOption.categories')
            ->where('school_year_id', $schoolYearId)
            ->whereIn('status', ['submitted', 'checked'])
            ->whereHas('quarters.students', fn ($query) => $query->where('student_id', $studentId))
            ->get();

        $savedGrades = [];

        foreach ($classRecords as $classRecord) {
            $gradeData = $this->computeGradeForRecord($classRecord, $studentId, $stanine);
            if ($gradeData === null) {
                continue;
            }

            $row = StudentAnnualGrade::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'school_year_id' => $schoolYearId,
                    'class_record_id' => $classRecord->id,
                ],
                array_merge($gradeData, [
                    'school_year_id' => $schoolYearId,
                    'subject_id' => $classRecord->subject_id,
                    'subject_name' => $classRecord->subject_name,
                    'computed_at' => Carbon::now(),
                ])
            );

            $savedGrades[] = $row;
        }

        return $savedGrades;
    }

    /**
     * Retrieve the transcript (list of grade rows) for a student and school year.
     * Returns already-computed rows; does NOT trigger computation.
     */
    public function getTranscript(int $studentId, int $schoolYearId): array
    {
        return StudentAnnualGrade::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->orderBy('subject_name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'subject_name' => $row->subject_name,
                'q1_ge' => $row->q1_ge,
                'q2_ge' => $row->q2_ge,
                'q3_ge' => $row->q3_ge,
                'q4_ge' => $row->q4_ge,
                'final_ge' => $row->final_ge,
                'remarks' => $row->remarks,
                'passed' => $row->isPassed(),
                'is_locked' => $row->is_locked,
                'computed_at' => $row->computed_at?->toDateTimeString(),
            ])
            ->toArray();
    }

    /**
     * Compute GWA from stored annual grades and upsert into student_academic_standing.
     * Uses the active RetentionPolicy to determine honors and standing.
     */
    public function computeStanding(
        int $studentId,
        int $schoolYearId,
        int $gradeLevel,
        ?int $processedBy = null,
    ): StudentAcademicStanding {
        $grades = StudentAnnualGrade::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->whereNotNull('final_ge')
            ->get();

        $subjectCount = $grades->count();
        $failedCount = $grades->filter(fn ($g) => (float) $g->final_ge > 3.0)->count();
        $gwa = $subjectCount > 0
            ? round($grades->avg(fn ($g) => (float) $g->final_ge), 3)
            : null;

        $policy = $this->resolvePolicy($schoolYearId, $gradeLevel);
        $honors = $this->determineHonors($gwa, $failedCount, $policy);
        $standing = $policy
            ? $policy->determineStanding($failedCount, $gwa ? $this->geToGwaPct($gwa) : 0)
            : ($failedCount >= 3 ? 'retained' : 'promoted');

        return StudentAcademicStanding::updateOrCreate(
            ['student_id' => $studentId, 'school_year_id' => $schoolYearId],
            [
                'gwa' => $gwa,
                'subject_count' => $subjectCount,
                'failed_subject_count' => $failedCount,
                'honors' => $honors,
                'standing' => ucfirst($standing),
                'processed_by' => $processedBy ?? Auth::id(),
                'processed_at' => Carbon::now(),
            ]
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Compute Q1-Q4 running grades and final grade for a single class record / student.
     * Returns null if the student is not found in any quarter.
     */
    private function computeGradeForRecord(
        ClassRecord $classRecord,
        int $studentId,
        array $stanine,
    ): ?array {
        $runningGEs = [];

        for ($q = 1; $q <= 4; $q++) {
            $quarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
                ->where('quarter', $q)
                ->with(['assessments.gradingCategory', 'students.scores'])
                ->first();

            if (! $quarter || $quarter->students->isEmpty()) {
                $runningGEs[$q] = null;

                continue;
            }

            // Match student by student_id (references students.id)
            $studentRow = $quarter->students->firstWhere('student_id', $studentId);

            if (! $studentRow) {
                $runningGEs[$q] = null;

                continue;
            }

            $quarterGE = $this->computeSingleQuarterGE($classRecord, $quarter, $studentRow, $stanine);

            // Apply running-grade rolling formula manually (avoids cross-quarter ID mismatch)
            if ($q === 1 || $runningGEs[$q - 1] === null) {
                $runningGEs[$q] = $quarterGE;
            } else {
                $raw = ($quarterGE * (2 / 3)) + ($runningGEs[$q - 1] * (1 / 3));
                // floor to 3 decimal places (matches GradeComputationService::floorToDecimals)
                $runningGEs[$q] = floor($raw * 1000) / 1000;
            }
        }

        // Must have at least Q1 to compute anything
        if ($runningGEs[1] === null) {
            return null;
        }

        $q1 = $runningGEs[1];
        $q2 = $runningGEs[2] ?? $q1;
        $q3 = $runningGEs[3] ?? $q2;
        $q4 = $runningGEs[4] ?? $q3;

        $finalResult = $this->grader->computeFinalGrade($q1, $q2, $q3, $q4, $stanine);
        $finalGE = $finalResult['finalGE'];

        return [
            'student_id' => $studentId,
            'class_record_id' => $classRecord->id,
            'q1_ge' => $q1,
            'q2_ge' => $runningGEs[2],
            'q3_ge' => $runningGEs[3],
            'q4_ge' => $runningGEs[4],
            'final_ge' => $finalGE,
            'remarks' => $finalGE <= 3.0 ? 'Passed' : 'Failed',
        ];
    }

    /**
     * Compute the raw quarter GE for a single student in a single quarter.
     * Passes the student as "Q1" to the grader so no rolling is applied here —
     * rolling is applied manually in computeGradeForRecord().
     */
    private function computeSingleQuarterGE(
        ClassRecord $classRecord,
        ClassRecordQuarter $quarter,
        $studentRow,
        array $stanine,
    ): float {
        $categories = $classRecord->gradingOption->categories
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'code' => $cat->code,
                'weight' => (float) $cat->weight,
                'assessments' => $quarter->assessments
                    ->where('grading_category_id', $cat->id)
                    ->sortBy('sort_order')
                    ->map(fn ($a) => ['id' => $a->id, 'maxScore' => (float) $a->max_score])
                    ->values()
                    ->toArray(),
            ])
            ->toArray();

        $studentData = [[
            'id' => $studentRow->id,
            'scores' => $studentRow->scores
                ->mapWithKeys(fn ($s) => [
                    $s->class_record_assessment_id => $s->score !== null ? (float) $s->score : null,
                ])
                ->toArray(),
        ]];

        // Pass quarter=1 so the grader treats this as a standalone quarter (no rolling)
        $result = $this->grader->computeFullClassRecord([
            'quarter' => 1,
            'gradingOption' => ['categories' => $categories],
            'students' => $studentData,
            'stanineLookup' => $stanine,
            'previousQuarterGrades' => [],
        ]);

        return (float) ($result['students'][0]['runningGrade'] ?? 5.0);
    }

    /**
     * Find the active retention policy for a given school year and grade level.
     * Prefers school-year-specific policies; falls back to global default.
     */
    private function resolvePolicy(int $schoolYearId, int $gradeLevel): ?RetentionPolicy
    {
        $band = $gradeLevel <= 10 ? 'jhs' : 'shs';

        // Try school-year + grade band specific
        $policy = RetentionPolicy::active()
            ->where('school_year_id', $schoolYearId)
            ->whereIn('grade_band', [$band, 'all'])
            ->orderByRaw("CASE grade_band WHEN '{$band}' THEN 0 ELSE 1 END")
            ->first();

        // Fallback: global default
        return $policy ?? RetentionPolicy::active()
            ->whereNull('school_year_id')
            ->whereIn('grade_band', [$band, 'all'])
            ->orderByRaw("CASE grade_band WHEN '{$band}' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * Determine honors label using the policy's percentage-based cutoffs.
     * GWA is in GE scale (1.0–5.0); we convert to a rough percentage for comparison.
     */
    private function determineHonors(?float $gwaGE, int $failedCount, ?RetentionPolicy $policy): string
    {
        // Cannot receive honors if any subject was failed
        if ($failedCount > 0 || $gwaGE === null) {
            return 'None';
        }

        if ($policy === null) {
            // Default DepEd Order 8 GE thresholds
            if ($gwaGE <= 1.25) {
                return 'With Highest Honors';
            }
            if ($gwaGE <= 1.50) {
                return 'With High Honors';
            }
            if ($gwaGE <= 1.75) {
                return 'With Honors';
            }

            return 'None';
        }

        // Policy uses percentage-based cutoffs; convert GE to approximate percentage
        $gwaPct = $this->geToGwaPct($gwaGE);

        if ($gwaPct >= (float) $policy->honors_highest_cutoff) {
            return 'With Highest Honors';
        }
        if ($gwaPct >= (float) $policy->honors_high_cutoff) {
            return 'With High Honors';
        }
        if ($gwaPct >= (float) $policy->honors_regular_cutoff) {
            return 'With Honors';
        }

        return 'None';
    }

    /**
     * Convert GE (1.0–5.0) to an approximate percentage (0–100).
     * Linear interpolation: GE 1.0 → 100%, GE 5.0 → 0%.
     * Provides rough parity with the DepEd percentage-based honors thresholds.
     */
    private function geToGwaPct(float $ge): float
    {
        // GE 1.0 = 100%, GE 3.0 = 50%, GE 5.0 = 0% (linear)
        return max(0, min(100, round(100 - (($ge - 1.0) / 4.0) * 100, 2)));
    }
}
