<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassRecordStudentController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    private function resolveQuarter(ClassRecord $classRecord, int $q): ClassRecordQuarter
    {
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');
        return ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $classRecord->id, 'quarter' => $q],
            ['is_locked' => false]
        );
    }

    // ── GET /class-records/{cr}/quarters/{q}/students/from-enrollment ────────

    public function fromEnrollment(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_unless($classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year.');

        // Block auto-populate for elective subjects
        $subjectType = $classRecord->subject?->subject_type;
        abort_if(
            $subjectType === 'elective',
            422,
            'Elective subjects cannot be auto-populated. Students choose their electives individually.'
        );

        abort_unless(
            $classRecord->section_id,
            422,
            'This class record is not linked to a section. Edit the record and set a section first.'
        );

        $currentSyId = SchoolYear::where('is_current', true)->value('id');

        // Pull enrolled students for this section
        $enrollments = StudentEnrollment::where('section_id', $classRecord->section_id)
            ->where('school_year_id', $currentSyId)
            ->where('status', 'enrolled')
            ->pluck('student_id');

        if ($enrollments->isEmpty()) {
            return response()->json([
                'students' => [],
                'message'  => 'No enrolled students found for this section.',
            ]);
        }

        $students = DB::table('students')
            ->whereIn('id', $enrollments)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get(['id', 'lastname', 'firstname', 'middlename', 'sex']);

        $rows = $students->values()->map(fn ($s, $idx) => [
            'id'              => null,
            'sequence_number' => $idx + 1,
            'family_name'     => strtoupper(trim($s->lastname ?? '')),
            'given_name'      => strtoupper(trim($s->firstname ?? '')),
            'middle_initial'  => $s->middlename ? strtoupper(substr(trim($s->middlename), 0, 1)) : '',
            'sex'             => $s->sex === 'Female' ? 'F' : ($s->sex === 'Male' ? 'M' : strtoupper(substr($s->sex ?? 'M', 0, 1))),
            'student_id'      => $s->id,
            '_delete'         => false,
        ]);

        return response()->json([
            'students' => $rows,
            'count'    => $rows->count(),
        ]);
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
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

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

    // ── GET /class-records/{cr}/quarters/{q}/students/template ───────────────

    public function template(ClassRecord $classRecord, int $q): StreamedResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);

        $filename = 'students_template_Q' . $q . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Sequence No.', 'Family Name', 'Given Name', 'Middle Initial', 'Sex (M/F)']);
            // Sample rows so the teacher knows the expected format
            fputcsv($out, [1, 'DELA CRUZ', 'JUAN', 'S', 'M']);
            fputcsv($out, [2, 'SANTOS', 'MARIA', 'L', 'F']);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/students/import ────────────────

    public function import(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before importing students.');

        $validated = $request->validate([
            'rows'                   => 'required|array|min:1|max:500',
            'rows.*.sequence_number' => 'required|integer|min:1',
            'rows.*.family_name'     => 'required|string|max:255',
            'rows.*.given_name'      => 'required|string|max:255',
            'rows.*.middle_initial'  => 'nullable|string|max:5',
            'rows.*.sex'             => 'required|in:M,F',
        ]);

        $upserted = 0;
        foreach ($validated['rows'] as $item) {
            ClassRecordStudent::updateOrCreate(
                [
                    'class_record_quarter_id' => $quarter->id,
                    'sequence_number'         => $item['sequence_number'],
                ],
                [
                    'family_name'    => strtoupper(trim($item['family_name'])),
                    'given_name'     => strtoupper(trim($item['given_name'])),
                    'middle_initial' => isset($item['middle_initial']) ? strtoupper(trim($item['middle_initial'])) : null,
                    'sex'            => strtoupper(trim($item['sex'])),
                    'is_active'      => true,
                ]
            );
            $upserted++;
        }

        return response()->json([
            'message' => "{$upserted} student(s) imported successfully.",
        ]);
    }
}
