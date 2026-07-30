<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingOption;
use App\Models\ClassRecord\StanineLookup;
use App\Services\ClassRecord\ClassRecordExcelService;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use App\Services\ClassRecord\ClassRecordPdfService;
use App\Services\ClassRecord\GradeComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassRecordQuarterController extends Controller
{
    public function __construct(
        private readonly GradeComputationService $grader,
        private readonly ClassRecordExcelService $excelService,
        private readonly ClassRecordPdfService $pdfService,
        private readonly ClassRecordMonitorScopeService $monitorScope,
    ) {}

    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    /** Read-only access: admin, the owning teacher, or a scoped monitor (CID Chief / AUH). */
    private function canView(ClassRecord $classRecord): bool
    {
        return $classRecord->canView(Auth::user())
            || $this->monitorScope->canView(Auth::user(), $classRecord);
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
        abort_unless($classRecord->canEdit(Auth::user()), 403);

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
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 422, 'Quarter is already locked.');

        $quarter->update(['is_locked' => true, 'locked_at' => now()]);

        return response()->json(['message' => "Quarter {$q} locked successfully."]);
    }

    // ── POST /class-records/{classRecord}/quarters/{q}/unlock ─────────────────

    public function unlock(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only administrators can unlock a quarter.');
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        $quarter->update(['is_locked' => false, 'locked_at' => null]);

        return response()->json(['message' => "Quarter {$q} unlocked."]);
    }

    // ── PUT /class-records/{classRecord}/quarters/{q}/grading-option ──────────

    public function setGradingOption(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $data = $request->validate([
            'grading_option_id' => 'required|integer|exists:grading_options,id',
        ]);

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before changing the grading option.');

        $option = GradingOption::findOrFail($data['grading_option_id']);
        abort_unless($option->is_active, 422, 'Select an active grading option.');
        abort_unless($option->appliesToQuarter($q), 422, "This grading option does not apply to Quarter {$q}.");

        // Changing the option re-defines the categories that assessments/scores
        // hang off — block while this quarter still has assessments so nothing
        // is silently orphaned. The teacher must clear them first.
        $hasAssessments = ClassRecordAssessment::where('class_record_quarter_id', $quarter->id)->exists();
        abort_if(
            $hasAssessments,
            422,
            'This quarter already has assessments under the current grading option. Clear this quarter\'s assessments before changing its grading option.',
        );

        $quarter->update(['grading_option_id' => $option->id]);

        return response()->json([
            'message' => "Quarter {$q} grading option updated.",
            'data' => $quarter->fresh()->load('gradingOption.categories'),
        ]);
    }

    // ── GET /class-records/{classRecord}/quarters/{q}/grades ──────────────────

    public function grades(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        $quarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->with(['assessments.gradingCategory', 'students.scores'])
            ->first();

        if (! $quarter) {
            return response()->json(['students' => []]);
        }

        // Resolve the grading option in force for THIS quarter (per-quarter
        // override, else the record default), then compute over its leaves.
        $optionId = ($quarter->grading_option_id ?? $classRecord->grading_option_id);
        $gradingOption = GradingOption::with('categories')->find($optionId);
        $leaves = $gradingOption ? $gradingOption->leafCategories() : collect();

        // Build categories structure for computation service (leaves only)
        $categories = $leaves->map(function ($cat) use ($quarter) {
            $assessments = $quarter->assessments
                ->where('grading_category_id', $cat->id)
                ->sortBy('sort_order')
                ->map(fn ($a) => ['id' => $a->id, 'maxScore' => (float) $a->max_score])
                ->values()
                ->toArray();

            return [
                'id' => $cat->id,
                'code' => $cat->code,
                'weight' => (float) $cat->weight,
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
                $previousByMasterStudent = $this->getPreviousRunningGrades($classRecord, $q - 1);
                $previousGrades = $quarter->students
                    ->filter(fn ($student) => $student->student_id !== null)
                    ->mapWithKeys(fn ($student) => [
                        $student->id => $previousByMasterStudent[$student->student_id] ?? null,
                    ])
                    ->filter(fn ($grade) => $grade !== null)
                    ->all();
            }
        }

        // Load stanine lookup
        $stanine = StanineLookup::orderByDesc('percentage')->get()->toArray();

        $result = $this->grader->computeFullClassRecord([
            'quarter' => $q,
            'gradingOption' => ['categories' => $categories],
            'students' => $students,
            'stanineLookup' => $stanine,
            'previousQuarterGrades' => $previousGrades,
        ]);

        // Attach student names to results
        $studentMap = $quarter->students->keyBy('id');
        $result['students'] = array_map(function ($s) use ($studentMap) {
            $model = $studentMap->get($s['studentId']);

            return array_merge($s, [
                'familyName' => $model?->family_name,
                'givenName' => $model?->given_name,
                'middleInitial' => $model?->middle_initial,
                'sex' => $model?->sex,
                'sequenceNumber' => $model?->sequence_number,
                'masterStudentId' => $model?->student_id,
            ]);
        }, $result['students']);

        return response()->json($result);
    }

    /**
     * Recursively get the running grades from a previous quarter.
     * Returns [ students.id => runningGrade (float) ].
     */
    private function getPreviousRunningGrades(ClassRecord $classRecord, int $q): array
    {
        // Compute grades for the previous quarter by re-running the service
        // This is intentionally recursive for Q3/Q4 but depth is always ≤ 3
        $response = $this->grades($classRecord, $q);
        $data = json_decode($response->getContent(), true);

        $result = [];
        foreach ($data['students'] ?? [] as $s) {
            if (! empty($s['masterStudentId'])) {
                $result[$s['masterStudentId']] = $s['runningGrade'] ?? null;
            }
        }

        return $result;
    }

    // ── GET /class-records/{cr}/quarters/{q}/export ───────────────────────────

    public function exportQuarter(ClassRecord $classRecord, int $q): BinaryFileResponse
    {
        abort_unless($this->canView($classRecord), 403);
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        $classRecord->loadMissing([
            'gradingOption.categories',
            'quarters.assessments.gradingCategory',
            'quarters.students',
        ]);

        $tempFile = $this->excelService->exportQuarter($classRecord, $q);
        $filename = $this->buildFilename($classRecord, $q);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    // ── GET /class-records/{cr}/export ────────────────────────────────────────

    public function exportAll(ClassRecord $classRecord): BinaryFileResponse
    {
        abort_unless($this->canView($classRecord), 403);

        $classRecord->loadMissing([
            'gradingOption.categories',
            'quarters.assessments.gradingCategory',
            'quarters.students',
        ]);

        $tempFile = $this->excelService->exportAll($classRecord);
        $filename = $this->buildFilename($classRecord);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }

    // ── GET /class-records/{cr}/quarters/{q}/pdf ──────────────────────────────

    public function exportPdf(ClassRecord $classRecord, int $q): Response
    {
        abort_unless($this->canView($classRecord), 403);
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        $classRecord->load(['teacher:id,name,position', 'gradingOption.categories']);

        $pdfBytes = $this->pdfService->exportQuarter($classRecord, $q);

        $subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $classRecord->subject_name);
        $section = preg_replace('/[^A-Za-z0-9_-]/', '_', $classRecord->year_level_section);
        $sy = str_replace('-', '_', $classRecord->school_year);
        $filename = "{$subject}_{$section}_Q{$q}_{$sy}.pdf";

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function buildFilename(ClassRecord $classRecord, ?int $quarter = null): string
    {
        $subject = preg_replace('/[^A-Za-z0-9_-]/', '_', $classRecord->subject_name);
        $section = preg_replace('/[^A-Za-z0-9_-]/', '_', $classRecord->year_level_section);
        $sy = str_replace('-', '_', $classRecord->school_year);
        $qPart = $quarter ? "_Q{$quarter}" : '_All';

        return "{$subject}_{$section}{$qPart}_{$sy}.xlsx";
    }
}
