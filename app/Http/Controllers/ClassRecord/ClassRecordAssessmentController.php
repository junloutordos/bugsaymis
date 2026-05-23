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
use Illuminate\Support\Facades\DB;

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

        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');
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

    // ── POST /class-records/{cr}/quarters/{q}/assessments/copy-from ──────────

    public function copyFrom(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $targetQuarter = $this->resolveQuarter($classRecord, $q);
        abort_if($targetQuarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'source_quarter' => 'required|integer|in:1,2,3,4',
        ]);

        $sourceQ = (int) $validated['source_quarter'];
        abort_if($sourceQ === $q, 422, 'Source and target quarters must be different.');

        $sourceQuarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $sourceQ)
            ->first();

        abort_if(! $sourceQuarter, 422, "Quarter {$sourceQ} has no assessments to copy from.");

        $sourceAssessments = ClassRecordAssessment::where('class_record_quarter_id', $sourceQuarter->id)
            ->orderBy('sort_order')
            ->get();

        abort_if($sourceAssessments->isEmpty(), 422, "Quarter {$sourceQ} has no assessments yet.");

        $targetCount = ClassRecordAssessment::where('class_record_quarter_id', $targetQuarter->id)->count();
        abort_if($targetCount > 0, 422, 'This quarter already has assessments. Clear them before copying.');

        $copied = DB::transaction(function () use ($sourceAssessments, $targetQuarter) {
            return $sourceAssessments->map(fn ($src) => ClassRecordAssessment::create([
                'class_record_quarter_id' => $targetQuarter->id,
                'grading_category_id'     => $src->grading_category_id,
                'assessment_number'       => $src->assessment_number,
                'title'                   => $src->title,
                'activity_date'           => null,
                'max_score'               => $src->max_score,
                'sort_order'              => $src->sort_order,
            ]))->values()->all();
        });

        return response()->json([
            'message' => count($copied) . ' assessment(s) copied from Q' . $sourceQ . '.',
            'data'    => $copied,
        ]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/assessments/copy-from-record ───

    public function copyFromRecord(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $targetQuarter = $this->resolveQuarter($classRecord, $q);
        abort_if($targetQuarter->is_locked, 403, 'Quarter is locked.');

        $validated = $request->validate([
            'source_class_record_id' => 'required|integer|exists:class_records,id',
            'source_quarter'         => 'required|integer|in:1,2,3,4',
        ]);

        $sourceRecord = ClassRecord::findOrFail($validated['source_class_record_id']);
        abort_unless(
            $this->isAdmin() || $sourceRecord->teacher_id === Auth::id(),
            403,
            'You do not have access to that class record.'
        );
        abort_if(
            strtolower($sourceRecord->subject_name) !== strtolower($classRecord->subject_name),
            422,
            'Source class record must be for the same subject.'
        );

        $sourceQuarter = ClassRecordQuarter::where('class_record_id', $sourceRecord->id)
            ->where('quarter', $validated['source_quarter'])
            ->first();

        abort_if(! $sourceQuarter, 422, 'Source quarter not found.');

        $sourceAssessments = ClassRecordAssessment::where('class_record_quarter_id', $sourceQuarter->id)
            ->orderBy('sort_order')
            ->get();

        abort_if($sourceAssessments->isEmpty(), 422, 'Source quarter has no assessments.');

        $targetCount = ClassRecordAssessment::where('class_record_quarter_id', $targetQuarter->id)->count();
        abort_if($targetCount > 0, 422, 'This quarter already has assessments. Clear them before copying.');

        $copied = DB::transaction(function () use ($sourceAssessments, $targetQuarter) {
            return $sourceAssessments->map(fn ($src) => ClassRecordAssessment::create([
                'class_record_quarter_id' => $targetQuarter->id,
                'grading_category_id'     => $src->grading_category_id,
                'assessment_number'       => $src->assessment_number,
                'title'                   => $src->title,
                'activity_date'           => null,
                'max_score'               => $src->max_score,
                'sort_order'              => $src->sort_order,
            ]))->values()->all();
        });

        return response()->json([
            'message' => count($copied) . ' assessment(s) copied.',
            'data'    => $copied,
        ]);
    }
}
