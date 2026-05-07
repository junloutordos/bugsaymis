<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassRecordAssessmentController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'AUH', 'CID Chief']);
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');
        return ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $classRecord->id, 'quarter' => $q],
            ['is_locked' => false]
        );
    }

    // ── GET /class-records/{cr}/quarters/{q}/assessments ─────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $quarter     = $this->resolveQuarter($classRecord, $q);
        $assessments = ClassRecordAssessment::with('gradingCategory')
            ->where('class_record_quarter_id', $quarter->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json($assessments);
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments ────────────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before editing assessments.');

        $validated = $request->validate([
            'assessments'                         => 'required|array|min:1',
            'assessments.*.grading_category_id'   => 'required|integer|exists:grading_categories,id',
            'assessments.*.assessment_number'      => 'required|integer|min:1',
            'assessments.*.title'                  => 'required|string|max:255',
            'assessments.*.activity_date'          => 'nullable|date',
            'assessments.*.max_score'              => 'required|numeric|min:0.01',
            'assessments.*.sort_order'             => 'sometimes|integer|min:0',
        ]);

        $upserted = [];
        foreach ($validated['assessments'] as $i => $item) {
            $assessment = ClassRecordAssessment::updateOrCreate(
                [
                    'class_record_quarter_id' => $quarter->id,
                    'grading_category_id'     => $item['grading_category_id'],
                    'assessment_number'       => $item['assessment_number'],
                ],
                [
                    'title'         => $item['title'],
                    'activity_date' => $item['activity_date'] ?? null,
                    'max_score'     => $item['max_score'],
                    'sort_order'    => $item['sort_order'] ?? $i,
                ]
            );
            $upserted[] = $assessment;
        }

        return response()->json([
            'message' => count($upserted) . ' assessment(s) saved.',
            'data'    => $upserted,
        ]);
    }
}
