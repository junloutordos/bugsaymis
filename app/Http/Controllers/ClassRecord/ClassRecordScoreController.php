<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClassRecordScoreController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'AUH', 'CID Chief']);
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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

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
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');
        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before entering scores.');

        $validated = $request->validate([
            'scores'                      => 'required|array|min:1',
            'scores.*.student_id'         => 'required|integer|exists:class_record_students,id',
            'scores.*.assessment_id'      => 'required|integer|exists:class_record_assessments,id',
            'scores.*.score'              => 'nullable|numeric|min:0',
        ]);

        // Collect assessment max_scores for validation
        $assessmentIds = collect($validated['scores'])->pluck('assessment_id')->unique();
        $maxScores     = ClassRecordAssessment::whereIn('id', $assessmentIds)
            ->pluck('max_score', 'id');

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
