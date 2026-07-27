<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\StanineLookup;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use App\Services\ClassRecord\GradeComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ClassRecordFinalGradeController extends Controller
{
    public function __construct(
        private readonly GradeComputationService $grader,
        private readonly ClassRecordMonitorScopeService $monitorScope,
    ) {}

    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    /** Read-only access: admin, the owning teacher, or a scoped monitor (CID Chief / AUH). */
    private function canView(ClassRecord $classRecord): bool
    {
        return $this->isAdmin()
            || $classRecord->teacher_id === Auth::id()
            || $this->monitorScope->canView(Auth::user(), $classRecord);
    }

    /**
     * GET /class-records/{classRecord}/final-grades
     *
     * Returns per-student final annual grade (average of all 4 quarterly GEs).
     * Requires at least one student record somewhere in each quarter.
     *
     * A subject can be split into category-labeled sibling class records
     * (e.g. STEM Research "Ongoing" -> "Completed") that a student moves
     * between mid-year. Neither sibling alone necessarily spans all 4
     * quarters for every student, so grades are resolved per student, per
     * quarter, from whichever sibling in the group had them active that
     * quarter, then merged before computing the annual grade.
     */
    public function index(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->canView($classRecord), 403);

        $group = collect([$classRecord])->concat($classRecord->siblingsQuery()->get());
        $group->each(fn ($cr) => $cr->loadMissing('gradingOption.categories'));

        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        // Compute grades for each quarter across the whole sibling group,
        // merging by student, chaining previous running grades forward.
        $previousGrades = [];
        $quarterResults = [];

        for ($q = 1; $q <= 4; $q++) {
            $merged = [];
            foreach ($group as $sibling) {
                foreach ($this->computeQuarterGrades($sibling, $q, $previousGrades, $stanine) as $row) {
                    if ($row['masterStudentId'] !== null) {
                        $merged[$row['masterStudentId']] = $row;
                    }
                }
            }
            $quarterResults[$q] = $merged;

            $previousGrades = [];
            foreach ($merged as $studentId => $row) {
                $previousGrades[$studentId] = $row['runningGrade'];
            }
        }

        // If any quarter produced no students anywhere in the group, we cannot compute a final grade
        if (collect($quarterResults)->contains(fn ($rows) => empty($rows))) {
            return response()->json([
                'students' => [],
                'message' => 'One or more quarters have no student data. Final grades cannot be computed.',
            ]);
        }

        // Canonical roster = every student who appeared in ANY quarter across
        // the group (not just Q1 — a student may join a sibling record mid-year).
        $allStudentIds = collect();
        foreach ($quarterResults as $rows) {
            $allStudentIds = $allStudentIds->merge(array_keys($rows));
        }
        $allStudentIds = $allStudentIds->unique()->values();

        $students = [];
        foreach ($allStudentIds as $masterStudentId) {
            $ge = [];
            $anchorRow = null;
            foreach ([1, 2, 3, 4] as $q) {
                $row = $quarterResults[$q][$masterStudentId] ?? null;
                $ge[$q] = $row['gradeEquivalent'] ?? null;
                $anchorRow ??= $row;
            }
            if (! $anchorRow) {
                continue;
            }

            // Forward-fill gaps (student absent from a later quarter's data);
            // back-fill any leading gap (student's first-ever quarter isn't Q1)
            // with their earliest known GE so the average never sees a null.
            for ($q = 2; $q <= 4; $q++) {
                $ge[$q] ??= $ge[$q - 1];
            }
            if ($ge[1] === null) {
                $earliest = collect($ge)->first(fn ($v) => $v !== null);
                foreach ([1, 2, 3, 4] as $q) {
                    $ge[$q] ??= $earliest;
                }
            }

            $final = $this->grader->computeFinalGrade($ge[1], $ge[2], $ge[3], $ge[4], $stanine);

            $students[] = [
                'studentId' => $masterStudentId,
                'sequenceNumber' => $anchorRow['sequenceNumber'],
                'familyName' => $anchorRow['familyName'],
                'givenName' => $anchorRow['givenName'],
                'middleInitial' => $anchorRow['middleInitial'],
                'sex' => $anchorRow['sex'],
                'q1GE' => $ge[1],
                'q2GE' => $ge[2],
                'q3GE' => $ge[3],
                'q4GE' => $ge[4],
                'finalGE' => $final['finalGE'],
                'adjectival' => $final['adjectivalEquivalent'],
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
        int $q,
        array $previousGrades,
        array $stanine,
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
                'id' => $cat->id,
                'code' => $cat->code,
                'weight' => (float) $cat->weight,
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
                'id' => $student->id,
                'scores' => $student->scores->mapWithKeys(fn ($s) => [
                    $s->class_record_assessment_id => $s->score !== null ? (float) $s->score : null,
                ])->toArray(),
            ];
        })->toArray();

        $previousForQuarterRows = $quarter->students
            ->filter(fn ($student) => $student->student_id !== null)
            ->mapWithKeys(fn ($student) => [
                $student->id => $previousGrades[$student->student_id] ?? null,
            ])
            ->filter(fn ($grade) => $grade !== null)
            ->all();

        $result = $this->grader->computeFullClassRecord([
            'quarter' => $q,
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
            'stanineLookup' => $stanine,
            'previousQuarterGrades' => $previousForQuarterRows,
        ]);

        $studentMap = $quarter->students->keyBy('id');

        return array_map(function ($row) use ($studentMap) {
            $model = $studentMap->get($row['studentId']);

            return array_merge($row, [
                'familyName' => $model?->family_name,
                'givenName' => $model?->given_name,
                'middleInitial' => $model?->middle_initial,
                'sex' => $model?->sex,
                'sequenceNumber' => $model?->sequence_number,
                'masterStudentId' => $model?->student_id,
            ]);
        }, $result['students']);
    }
}
