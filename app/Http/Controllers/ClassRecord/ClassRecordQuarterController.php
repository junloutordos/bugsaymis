<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\StanineLookup;
use App\Services\ClassRecord\GradeComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassRecordQuarterController extends Controller
{
    public function __construct(private readonly GradeComputationService $grader) {}

    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'AUH']);
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        return ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $classRecord->id, 'quarter' => $q],
            ['is_locked' => false]
        );
    }

    // ── GET /class-records/{classRecord}/quarters/{q} ─────────────────────────

    public function show(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);
        $quarter->load([
            'assessments.gradingCategory',
            'students',
        ]);

        return response()->json($quarter);
    }

    // ── POST /class-records/{classRecord}/quarters/{q}/lock ───────────────────

    public function lock(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 422, 'Quarter is already locked.');

        $quarter->update(['is_locked' => true, 'locked_at' => now()]);

        return response()->json(['message' => "Quarter {$q} locked successfully."]);
    }

    // ── POST /class-records/{classRecord}/quarters/{q}/unlock ─────────────────

    public function unlock(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only administrators can unlock a quarter.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        $quarter->update(['is_locked' => false, 'locked_at' => null]);

        return response()->json(['message' => "Quarter {$q} unlocked."]);
    }

    // ── GET /class-records/{classRecord}/quarters/{q}/grades ──────────────────

    public function grades(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        $quarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->with(['assessments.gradingCategory', 'students.scores'])
            ->first();

        if (! $quarter) {
            return response()->json(['students' => []]);
        }

        // Load grading option categories with weights
        $classRecord->load('gradingOption.categories');
        $gradingOption = $classRecord->gradingOption;

        // Build categories structure for computation service
        $categories = $gradingOption->categories->map(function ($cat) use ($quarter) {
            $assessments = $quarter->assessments
                ->where('grading_category_id', $cat->id)
                ->sortBy('sort_order')
                ->map(fn ($a) => ['id' => $a->id, 'maxScore' => (float) $a->max_score])
                ->values()
                ->toArray();

            return [
                'id'          => $cat->id,
                'code'        => $cat->code,
                'weight'      => (float) $cat->weight,
                'assessments' => $assessments,
            ];
        })->toArray();

        // Build student scores (sparse map)
        $students = $quarter->students->map(function ($student) {
            $scores = $student->scores->mapWithKeys(fn ($s) => [
                $s->class_record_assessment_id => $s->score !== null ? (float) $s->score : null,
            ])->toArray();

            return ['id' => $student->id, 'scores' => $scores];
        })->toArray();

        // Load previous quarter running grades (Q2-Q4)
        $previousGrades = [];
        if ($q > 1) {
            $prevQuarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
                ->where('quarter', $q - 1)
                ->with('students')
                ->first();

            if ($prevQuarter) {
                // We don't store computed grades — re-compute previous quarter to get running grade
                // For performance, we could cache; for now compute inline
                $previousGrades = $this->getPreviousRunningGrades($classRecord, $q - 1);
            }
        }

        // Load stanine lookup
        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        $result = $this->grader->computeFullClassRecord([
            'quarter'               => $q,
            'gradingOption'         => ['categories' => $categories],
            'students'              => $students,
            'stanineLookup'         => $stanine,
            'previousQuarterGrades' => $previousGrades,
        ]);

        // Attach student names to results
        $studentMap = $quarter->students->keyBy('id');
        $result['students'] = array_map(function ($s) use ($studentMap) {
            $model = $studentMap->get($s['studentId']);
            return array_merge($s, [
                'familyName'     => $model?->family_name,
                'givenName'      => $model?->given_name,
                'middleInitial'  => $model?->middle_initial,
                'sex'            => $model?->sex,
                'sequenceNumber' => $model?->sequence_number,
            ]);
        }, $result['students']);

        return response()->json($result);
    }

    /**
     * Recursively get the running grades from a previous quarter.
     * Returns [ studentId => runningGrade (float) ].
     */
    private function getPreviousRunningGrades(ClassRecord $classRecord, int $q): array
    {
        // Compute grades for the previous quarter by re-running the service
        // This is intentionally recursive for Q3/Q4 but depth is always ≤ 3
        $response = $this->grades($classRecord, $q);
        $data     = json_decode($response->getContent(), true);

        $result = [];
        foreach ($data['students'] ?? [] as $s) {
            $result[$s['studentId']] = $s['runningGrade'] ?? null;
        }
        return $result;
    }
}
