<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\StanineLookup;
use App\Services\ClassRecord\GradeComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ClassRecordFinalGradeController extends Controller
{
    public function __construct(
        private readonly GradeComputationService $grader,
    ) {}

    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    /**
     * GET /class-records/{classRecord}/final-grades
     *
     * Returns per-student final annual grade (average of all 4 quarterly GEs).
     * Requires all 4 quarters to have at least one student record.
     */
    public function index(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $classRecord->load('gradingOption.categories');
        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        // Compute grades for each quarter, chaining previous running grades
        $previousGrades = [];
        $quarterResults = [];

        for ($q = 1; $q <= 4; $q++) {
            $quarterData = $this->computeQuarterGrades($classRecord, $q, $previousGrades, $stanine);
            $quarterResults[$q] = $quarterData;

            // Feed running grades forward for the next quarter
            $previousGrades = [];
            foreach ($quarterData as $row) {
                $previousGrades[$row['studentId']] = $row['runningGrade'];
            }
        }

        // If any quarter produced no students, we cannot compute a final grade
        if (collect($quarterResults)->contains(fn ($rows) => empty($rows))) {
            return response()->json([
                'students' => [],
                'message'  => 'One or more quarters have no student data. Final grades cannot be computed.',
            ]);
        }

        // Merge per-quarter GEs and compute final grade per student
        // Use Q1's student list as the canonical roster
        $q1Students = collect($quarterResults[1])->keyBy('studentId');
        $q2ByStudent = collect($quarterResults[2])->keyBy('studentId');
        $q3ByStudent = collect($quarterResults[3])->keyBy('studentId');
        $q4ByStudent = collect($quarterResults[4])->keyBy('studentId');

        $students = [];
        foreach ($q1Students as $studentId => $q1Row) {
            $q1GE = $q1Row['gradeEquivalent'];
            $q2GE = $q2ByStudent[$studentId]['gradeEquivalent'] ?? $q1GE;
            $q3GE = $q3ByStudent[$studentId]['gradeEquivalent'] ?? $q1GE;
            $q4GE = $q4ByStudent[$studentId]['gradeEquivalent'] ?? $q1GE;

            $final = $this->grader->computeFinalGrade($q1GE, $q2GE, $q3GE, $q4GE, $stanine);

            $students[] = [
                'studentId'      => $studentId,
                'sequenceNumber' => $q1Row['sequenceNumber'],
                'familyName'     => $q1Row['familyName'],
                'givenName'      => $q1Row['givenName'],
                'middleInitial'  => $q1Row['middleInitial'],
                'sex'            => $q1Row['sex'],
                'q1GE'           => $q1GE,
                'q2GE'           => $q2GE,
                'q3GE'           => $q3GE,
                'q4GE'           => $q4GE,
                'finalGE'        => $final['finalGE'],
                'adjectival'     => $final['adjectivalEquivalent'],
            ];
        }

        usort($students, fn ($a, $b) => $a['sequenceNumber'] <=> $b['sequenceNumber']);

        return response()->json(['students' => $students]);
    }

    /**
     * Compute all students' grades for a given quarter.
     * Returns array of grade rows (same shape as ClassRecordQuarterController::grades).
     */
    private function computeQuarterGrades(
        ClassRecord $classRecord,
        int         $q,
        array       $previousGrades,
        array       $stanine,
    ): array {
        $quarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->with(['assessments.gradingCategory', 'students.scores'])
            ->first();

        if (! $quarter || $quarter->students->isEmpty()) {
            return [];
        }

        $categories = $classRecord->gradingOption->categories->map(function ($cat) use ($quarter) {
            return [
                'id'          => $cat->id,
                'code'        => $cat->code,
                'weight'      => (float) $cat->weight,
                'assessments' => $quarter->assessments
                    ->where('grading_category_id', $cat->id)
                    ->sortBy('sort_order')
                    ->map(fn ($a) => ['id' => $a->id, 'maxScore' => (float) $a->max_score])
                    ->values()
                    ->toArray(),
            ];
        })->toArray();

        $students = $quarter->students->map(function ($student) {
            return [
                'id'     => $student->id,
                'scores' => $student->scores->mapWithKeys(fn ($s) => [
                    $s->class_record_assessment_id => $s->score !== null ? (float) $s->score : null,
                ])->toArray(),
            ];
        })->toArray();

        $result = $this->grader->computeFullClassRecord([
            'quarter'               => $q,
            'gradingOption'         => ['categories' => $categories],
            'students'              => $students,
            'stanineLookup'         => $stanine,
            'previousQuarterGrades' => $previousGrades,
        ]);

        $studentMap = $quarter->students->keyBy('id');

        return array_map(function ($row) use ($studentMap) {
            $model = $studentMap->get($row['studentId']);
            return array_merge($row, [
                'familyName'     => $model?->family_name,
                'givenName'      => $model?->given_name,
                'middleInitial'  => $model?->middle_initial,
                'sex'            => $model?->sex,
                'sequenceNumber' => $model?->sequence_number,
            ]);
        }, $result['students']);
    }
}
