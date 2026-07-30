<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Services\ClassRecord\ClassRecordMonitorScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordScoreController extends Controller
{
    public function __construct(private readonly ClassRecordMonitorScopeService $monitorScope)
    {
    }

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
        return ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->firstOrFail();
    }

    // ── GET /class-records/{cr}/quarters/{q}/scores ───────────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->canView($classRecord), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);

        // Return as flat map: "studentId_assessmentId" => score
        $scores = ClassRecordScore::whereHas('student', fn ($sq) =>
                $sq->where('class_record_quarter_id', $quarter->id)
            )
            ->get(['class_record_student_id', 'class_record_assessment_id', 'score'])
            ->mapWithKeys(fn ($s) =>
                ["{$s->class_record_student_id}_{$s->class_record_assessment_id}" => $s->score]
            );

        return response()->json($scores);
    }

    // ── POST /class-records/{cr}/quarters/{q}/scores ──────────────────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        // Record-wide gate first (must be at least one of the record's
        // teachers, or admin) — the finer per-assessment subject check
        // happens below once we know which leaf category each score targets.
        abort_unless($classRecord->canEdit(Auth::user()), 403);

        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');
        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before entering scores.');

        $validated = $request->validate([
            'scores'                      => 'required|array|min:1',
            'scores.*.student_id'         => 'required|integer|exists:class_record_students,id',
            'scores.*.assessment_id'      => 'required|integer|exists:class_record_assessments,id',
            'scores.*.score'              => 'nullable|numeric|min:0',
        ]);

        // Collect assessment max_scores + owning leaf category's subject_id
        // for validation (max_score check, and PEHM subject-scoped gating).
        $assessmentIds = collect($validated['scores'])->pluck('assessment_id')->unique();
        $assessments   = ClassRecordAssessment::with('gradingCategory:id,subject_id,name')
            ->whereIn('id', $assessmentIds)
            ->get()
            ->keyBy('id');
        $maxScores = $assessments->map(fn ($a) => $a->max_score);

        // On a shared (e.g. PEHM) record, a score may only be written by the
        // teacher who owns that assessment's leaf category's subject — same
        // rule as the assessment upsert endpoint. Leaves with no subject_id
        // (the normal case) stay scoped to any of the record's teachers.
        if (! $this->isAdmin()) {
            $subjectIds = $assessments->pluck('gradingCategory.subject_id')->unique();
            foreach ($subjectIds as $subjectId) {
                abort_unless(
                    $classRecord->canEdit(Auth::user(), $subjectId),
                    403,
                    'You do not have edit access to one or more of these assessments.',
                );
            }
        }

        // Validate each score does not exceed max_score
        foreach ($validated['scores'] as $item) {
            if ($item['score'] !== null) {
                $max = $maxScores->get($item['assessment_id']);
                if ($max !== null && $item['score'] > (float) $max) {
                    return response()->json([
                        'message' => "Score {$item['score']} exceeds max score {$max} for assessment {$item['assessment_id']}.",
                    ], 422);
                }
            }
        }

        // Upsert using DB to avoid N+1 on unique constraint
        $now    = now();
        $rows   = [];
        foreach ($validated['scores'] as $item) {
            $rows[] = [
                'class_record_student_id'    => $item['student_id'],
                'class_record_assessment_id' => $item['assessment_id'],
                'score'                      => $item['score'],
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ];
        }

        DB::table('class_record_scores')->upsert(
            $rows,
            ['class_record_student_id', 'class_record_assessment_id'],
            ['score', 'updated_at']
        );

        return response()->json([
            'message' => count($rows) . ' score(s) saved.',
        ]);
    }
}
