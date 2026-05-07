<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassRecordStudentController extends Controller
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

    // ── GET /class-records/{cr}/quarters/{q}/students ────────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $quarter  = $this->resolveQuarter($classRecord, $q);
        $students = ClassRecordStudent::where('class_record_quarter_id', $quarter->id)
            ->where('is_active', true)
            ->orderBy('sequence_number')
            ->get();

        return response()->json($students);
    }

    // ── POST /class-records/{cr}/quarters/{q}/students ───────────────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before editing the roster.');

        $validated = $request->validate([
            'students'                   => 'required|array|min:1',
            'students.*.family_name'     => 'required|string|max:255',
            'students.*.given_name'      => 'required|string|max:255',
            'students.*.middle_initial'  => 'nullable|string|max:5',
            'students.*.sex'             => 'required|in:M,F',
            'students.*.sequence_number' => 'required|integer|min:1',
            'students.*.student_id'      => 'nullable|integer',
            'students.*.is_active'       => 'sometimes|boolean',
        ]);

        $upserted = [];
        foreach ($validated['students'] as $item) {
            $student = ClassRecordStudent::updateOrCreate(
                [
                    'class_record_quarter_id' => $quarter->id,
                    'sequence_number'         => $item['sequence_number'],
                ],
                [
                    'student_id'     => $item['student_id'] ?? null,
                    'family_name'    => $item['family_name'],
                    'given_name'     => $item['given_name'],
                    'middle_initial' => $item['middle_initial'] ?? null,
                    'sex'            => $item['sex'],
                    'is_active'      => $item['is_active'] ?? true,
                ]
            );
            $upserted[] = $student;
        }

        return response()->json([
            'message' => count($upserted) . ' student(s) saved.',
            'data'    => $upserted,
        ]);
    }
}
