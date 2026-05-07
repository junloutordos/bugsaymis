<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\GradingOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassRecordController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasAnyRole(['Administrator', 'AUH', 'CID Chief']);
    }

    // ── GET /class-records ────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = ClassRecord::with(['teacher:id,name', 'gradingOption:id,name'])
            ->orderByDesc('created_at');

        if (! $this->isAdmin()) {
            $query->where('teacher_id', Auth::id());
        }

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->query('school_year'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(30));
    }

    // ── POST /class-records ───────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id'         => 'nullable|integer|exists:subjects,id',
            'section_id'         => 'nullable|integer',
            'grading_option_id'  => 'required|integer|exists:grading_options,id',
            'school_year'        => 'required|string|max:20',
            'subject_name'       => 'required|string|max:255',
            'year_level_section' => 'required|string|max:255',
        ]);

        $record = ClassRecord::create(array_merge($validated, [
            'teacher_id' => Auth::id(),
            'status'     => 'draft',
        ]));

        return response()->json([
            'message' => 'Class record created successfully.',
            'data'    => $record->load('gradingOption'),
        ], 201);
    }

    // ── GET /class-records/{classRecord} ──────────────────────────────────────

    public function show(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $classRecord->load([
            'teacher:id,name,position',
            'gradingOption.categories',
            'quarters',
        ]);

        return response()->json($classRecord);
    }

    // ── PUT /class-records/{classRecord} ──────────────────────────────────────

    public function update(Request $request, ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if($classRecord->status === 'submitted' && ! $this->isAdmin(), 403,
            'Cannot edit a submitted class record.');

        $validated = $request->validate([
            'subject_id'         => 'nullable|integer|exists:subjects,id',
            'section_id'         => 'nullable|integer',
            'grading_option_id'  => 'sometimes|integer|exists:grading_options,id',
            'school_year'        => 'sometimes|string|max:20',
            'subject_name'       => 'sometimes|string|max:255',
            'year_level_section' => 'sometimes|string|max:255',
        ]);

        // Block grading option change after scores have been entered
        if (isset($validated['grading_option_id'])
            && $validated['grading_option_id'] != $classRecord->grading_option_id) {
            $hasScores = \App\Models\ClassRecord\ClassRecordScore::whereHas(
                'student.quarter.classRecord', fn ($q) => $q->where('id', $classRecord->id)
            )->exists();

            if ($hasScores) {
                return response()->json([
                    'message' => 'Cannot change grading option after scores have been entered.',
                    'errors'  => ['grading_option_id' => ['Cannot change grading option after scores have been entered.']],
                ], 422);
            }
        }

        $classRecord->update($validated);

        return response()->json([
            'message' => 'Class record updated.',
            'data'    => $classRecord->fresh(),
        ]);
    }

    // ── DELETE /class-records/{classRecord} ───────────────────────────────────

    public function destroy(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if($classRecord->status !== 'draft', 422,
            'Only draft class records can be deleted.');

        $classRecord->delete();

        return response()->json(['message' => 'Class record deleted.']);
    }

    // ── POST /class-records/{classRecord}/submit ──────────────────────────────

    public function submit(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if($classRecord->status !== 'draft', 422, 'Only draft records can be submitted.');

        $classRecord->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json(['message' => 'Class record submitted for checking.']);
    }

    // ── POST /class-records/{classRecord}/check ───────────────────────────────

    public function check(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only administrators can mark a record as checked.');
        abort_if($classRecord->status !== 'submitted', 422, 'Record must be submitted first.');

        $classRecord->update([
            'status'        => 'checked',
            'checked_at'    => now(),
            'checked_by_id' => Auth::id(),
        ]);

        return response()->json(['message' => 'Class record marked as checked.']);
    }
}
