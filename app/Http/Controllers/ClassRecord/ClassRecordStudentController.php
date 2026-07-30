<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\Student;
use App\Services\ClassRecord\ClassRecordRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassRecordStudentController extends Controller
{
    public function __construct(
        private readonly ClassRecordRosterService $rosters,
    ) {}

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
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_unless($classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year.');

        abort_if($this->rosters->isCrossSection($classRecord), 422,
            'Elective and Science Core subjects use cross-section student selection and cannot be auto-populated.');

        abort_unless(
            $classRecord->section_id,
            422,
            'This class record is not linked to a section. Edit the record and set a section first.'
        );

        $students = $this->rosters->candidates($classRecord, limit: 500);

        if ($students->isEmpty()) {
            return response()->json([
                'students' => [],
                'message' => 'No enrolled students found for this section.',
            ]);
        }

        $rows = $students->values()->map(fn ($s, $idx) => [
            'id' => null,
            'sequence_number' => $idx + 1,
            'family_name' => strtoupper(trim($s->lastname ?? '')),
            'given_name' => strtoupper(trim($s->firstname ?? '')),
            'middle_initial' => $s->middlename ? strtoupper(substr(trim($s->middlename), 0, 1)) : '',
            'sex' => $this->normalizeSex($s->sex),
            'student_id' => $s->student_id,
            '_delete' => false,
        ]);

        return response()->json([
            'students' => $rows,
            'count' => $rows->count(),
        ]);
    }

    // ── GET /class-records/{cr}/quarters/{q}/students/candidates ─────────────

    public function candidates(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_unless(in_array($q, [1, 2, 3, 4]), 422, 'Quarter must be 1-4.');

        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
        ]);

        $quarter = ClassRecordQuarter::where('class_record_id', $classRecord->id)
            ->where('quarter', $q)
            ->first();

        $selectedIds = $quarter
            ? ClassRecordStudent::where('class_record_quarter_id', $quarter->id)
                ->where('is_active', true)
                ->whereNotNull('student_id')
                ->pluck('student_id')
            : collect();

        $students = $this->rosters->candidates($classRecord, $validated['search'] ?? null)
            ->reject(fn ($student) => $selectedIds->contains((int) $student->student_id))
            ->values()
            ->map(fn ($student) => [
                'student_id' => (int) $student->student_id,
                'family_name' => strtoupper(trim((string) $student->lastname)),
                'given_name' => strtoupper(trim((string) $student->firstname)),
                'middle_initial' => $student->middlename
                    ? strtoupper(substr(trim((string) $student->middlename), 0, 1))
                    : '',
                'sex' => $this->normalizeSex($student->sex),
                'grade_level' => (int) $student->grade_level,
                'homeroom_section' => $student->homeroom_section,
                'lrn' => $student->lrn,
                'pisay_system_id' => $student->pisay_system_id,
            ]);

        return response()->json([
            'students' => $students,
            'is_cross_section' => $this->rosters->isCrossSection($classRecord),
            'grade_levels' => $this->rosters->gradeLevels($classRecord),
        ]);
    }

    // ── GET /class-records/{cr}/quarters/{q}/students ────────────────────────

    public function index(ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);

        $quarter = $this->resolveQuarter($classRecord, $q);
        $students = ClassRecordStudent::where('class_record_quarter_id', $quarter->id)
            ->where('is_active', true)
            ->orderBy('sequence_number')
            ->get();

        return response()->json($students);
    }

    // ── POST /class-records/{cr}/quarters/{q}/students/{classRecordStudent}/transfer ─
    // Moves an active roster row to a sibling class record (same subject +
    // section + teacher + SY, different category_label) for the same quarter
    // — e.g. STEM Research "Ongoing" -> "Completed" once a student finishes.
    // Earlier quarters are untouched; this only affects quarter $q onward.

    public function transfer(Request $request, ClassRecord $classRecord, int $q, ClassRecordStudent $classRecordStudent): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before transferring students.');
        abort_unless($classRecordStudent->class_record_quarter_id === $quarter->id, 404);
        abort_unless($classRecordStudent->is_active, 422, 'This student is not active in this quarter.');
        abort_unless($classRecordStudent->student_id, 422, 'Only students linked to an enrollment record can be transferred.');

        $validated = $request->validate([
            'target_class_record_id' => 'required|integer|exists:class_records,id',
        ]);

        $target = ClassRecord::findOrFail($validated['target_class_record_id']);
        $isSibling = $classRecord->siblingsQuery()->where('id', $target->id)->exists();
        abort_unless($isSibling, 422, 'The target must be a sibling category of this same subject/section/teacher.');
        abort_unless($target->canEdit(Auth::user()), 403);

        $targetQuarter = ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $target->id, 'quarter' => $q],
            ['is_locked' => false]
        );
        abort_if($targetQuarter->is_locked, 403, 'The target class record\'s Q'.$q.' is locked.');

        $studentName = $classRecordStudent->full_name;

        $targetRow = DB::transaction(function () use ($classRecordStudent, $targetQuarter) {
            $classRecordStudent->update(['is_active' => false]);

            $row = ClassRecordStudent::where('class_record_quarter_id', $targetQuarter->id)
                ->where('student_id', $classRecordStudent->student_id)
                ->first();

            $nextSeq = 1 + (int) ClassRecordStudent::where('class_record_quarter_id', $targetQuarter->id)
                ->where('is_active', true)
                ->max('sequence_number');

            $attributes = [
                'family_name' => $classRecordStudent->family_name,
                'given_name' => $classRecordStudent->given_name,
                'middle_initial' => $classRecordStudent->middle_initial,
                'sex' => $classRecordStudent->sex,
                'is_active' => true,
            ];

            if ($row) {
                $row->update($attributes + ['sequence_number' => $row->sequence_number ?? $nextSeq]);
            } else {
                $row = ClassRecordStudent::create($attributes + [
                    'class_record_quarter_id' => $targetQuarter->id,
                    'student_id' => $classRecordStudent->student_id,
                    'sequence_number' => $nextSeq,
                ]);
            }

            return $row;
        });

        return response()->json([
            'message' => "{$studentName} transferred to \"{$target->display_name}\" for Q{$q}.",
            'data' => $targetRow,
        ]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/students ───────────────────────

    public function upsert(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before editing the roster.');

        $validated = $request->validate([
            'students' => 'required|array|min:1',
            'students.*.id' => 'nullable|integer|distinct',
            'students.*.family_name' => 'required|string|max:255',
            'students.*.given_name' => 'required|string|max:255',
            'students.*.middle_initial' => 'nullable|string|max:5',
            'students.*.sex' => 'required|in:M,F',
            'students.*.sequence_number' => 'required|integer|min:1',
            'students.*.student_id' => 'nullable|integer|distinct',
        ]);

        // Safely backfill legacy name-only rows when exactly one eligible
        // enrollment matches the stored roster name within this class scope.
        $legacyRows = ClassRecordStudent::where('class_record_quarter_id', $quarter->id)
            ->whereIn('id', collect($validated['students'])->pluck('id')->filter())
            ->whereNull('student_id')
            ->get()
            ->keyBy('id');
        $eligibleCandidates = null;

        foreach ($validated['students'] as &$item) {
            if (! empty($item['student_id']) || empty($item['id'])) {
                continue;
            }

            $legacyRow = $legacyRows->get((int) $item['id']);
            if (! $legacyRow) {
                continue;
            }

            $eligibleCandidates ??= $this->rosters->candidates($classRecord, limit: 500);
            $matches = $eligibleCandidates->filter(fn ($candidate) => $this->normalizedName($candidate->lastname) === $this->normalizedName($legacyRow->family_name)
                && $this->normalizedName($candidate->firstname) === $this->normalizedName($legacyRow->given_name));

            if ($matches->count() === 1) {
                $item['student_id'] = (int) $matches->first()->student_id;
            }
        }
        unset($item);

        $linkedIds = collect($validated['students'])
            ->pluck('student_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (count($linkedIds) !== count(array_unique($linkedIds))) {
            throw ValidationException::withMessages([
                'students' => 'The same student cannot appear more than once in a quarter roster.',
            ]);
        }

        $eligibleIds = $this->rosters->eligibleStudentIds($classRecord, $linkedIds);
        if (count($eligibleIds) !== count($linkedIds)) {
            throw ValidationException::withMessages([
                'students' => 'One or more students are not actively enrolled within this class record’s permitted section or grade scope.',
            ]);
        }

        // A student split across sibling category records (e.g. STEM Research
        // "Ongoing"/"Completed") must be active in exactly one of them per
        // quarter — otherwise they'd be graded twice for the same subject.
        if ($linkedIds) {
            $siblingIds = $classRecord->siblingsQuery()->pluck('id');
            if ($siblingIds->isNotEmpty()) {
                $conflict = ClassRecordStudent::whereIn('student_id', $linkedIds)
                    ->where('is_active', true)
                    ->whereHas('quarter', fn ($query) => $query->where('quarter', $q)->whereIn('class_record_id', $siblingIds))
                    ->with('quarter.classRecord:id,subject_name,category_label')
                    ->first();

                if ($conflict) {
                    $siblingLabel = $conflict->quarter->classRecord->display_name;
                    throw ValidationException::withMessages([
                        'students' => "{$conflict->full_name} is already active in \"{$siblingLabel}\" for Q{$q}. Transfer or remove them there first.",
                    ]);
                }
            }
        }

        $studentSnapshots = Student::whereIn('id', $linkedIds)->get()->keyBy('id');

        $upserted = DB::transaction(function () use ($quarter, $validated, $studentSnapshots) {
            $existing = ClassRecordStudent::where('class_record_quarter_id', $quarter->id)
                ->lockForUpdate()
                ->get();
            $existingById = $existing->keyBy('id');
            $existingByStudent = $existing->whereNotNull('student_id')->keyBy('student_id');
            $keptIds = [];
            $saved = [];

            foreach ($validated['students'] as $item) {
                $row = null;
                if (! empty($item['id'])) {
                    $row = $existingById->get((int) $item['id']);
                    if (! $row) {
                        throw ValidationException::withMessages([
                            'students' => 'A roster row does not belong to this class record quarter.',
                        ]);
                    }
                } elseif (! empty($item['student_id'])) {
                    $row = $existingByStudent->get((int) $item['student_id']);
                }

                if (! $row) {
                    if (empty($item['student_id'])) {
                        throw ValidationException::withMessages([
                            'students' => 'New roster entries must be selected from the student database.',
                        ]);
                    }
                    $row = new ClassRecordStudent([
                        'class_record_quarter_id' => $quarter->id,
                    ]);
                }

                $studentId = ! empty($item['student_id']) ? (int) $item['student_id'] : null;
                if ($row->exists && $row->student_id !== null && $studentId !== (int) $row->student_id) {
                    throw ValidationException::withMessages([
                        'students' => 'An existing linked roster row cannot be reassigned to another student.',
                    ]);
                }

                if ($studentId) {
                    $snapshot = $studentSnapshots->get($studentId);
                    $row->fill([
                        'student_id' => $studentId,
                        'family_name' => strtoupper(trim((string) $snapshot->lastname)),
                        'given_name' => strtoupper(trim((string) $snapshot->firstname)),
                        'middle_initial' => $snapshot->middlename
                            ? strtoupper(substr(trim((string) $snapshot->middlename), 0, 1))
                            : null,
                        'sex' => $this->normalizeSex($snapshot->sex),
                    ]);
                } else {
                    // Preserve legacy name-only rows until a teacher links them.
                    abort_unless($row->exists && $row->student_id === null, 422,
                        'New roster entries must be selected from the student database.');
                    $row->fill([
                        'family_name' => strtoupper(trim($item['family_name'])),
                        'given_name' => strtoupper(trim($item['given_name'])),
                        'middle_initial' => strtoupper(trim((string) ($item['middle_initial'] ?? ''))) ?: null,
                        'sex' => $item['sex'],
                    ]);
                }

                $row->sequence_number = $item['sequence_number'];
                $row->is_active = true;
                $row->save();

                $keptIds[] = $row->id;
                $saved[] = $row;
            }

            ClassRecordStudent::where('class_record_quarter_id', $quarter->id)
                ->whereNotIn('id', $keptIds)
                ->update(['is_active' => false]);

            return $saved;
        });

        return response()->json([
            'message' => count($upserted).' student(s) saved.',
            'data' => $upserted,
        ]);
    }

    // ── GET /class-records/{cr}/quarters/{q}/students/template ───────────────

    public function template(ClassRecord $classRecord, int $q): StreamedResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);

        $filename = 'students_template_Q'.$q.'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Sequence No.', 'Student ID', 'Family Name', 'Given Name', 'Middle Initial', 'Sex (M/F)']);
            // Sample rows so the teacher knows the expected format
            fputcsv($out, [1, 1234, 'DELA CRUZ', 'JUAN', 'S', 'M']);
            fputcsv($out, [2, 5678, 'SANTOS', 'MARIA', 'L', 'F']);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ── POST /class-records/{cr}/quarters/{q}/students/import ────────────────

    public function import(Request $request, ClassRecord $classRecord, int $q): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');

        $quarter = $this->resolveQuarter($classRecord, $q);
        abort_if($quarter->is_locked, 403, 'Quarter is locked. Unlock it before importing students.');

        $validated = $request->validate([
            'rows' => 'required|array|min:1|max:500',
            'rows.*.sequence_number' => 'required|integer|min:1',
            'rows.*.student_id' => 'required|integer|distinct',
            'rows.*.family_name' => 'required|string|max:255',
            'rows.*.given_name' => 'required|string|max:255',
            'rows.*.middle_initial' => 'nullable|string|max:5',
            'rows.*.sex' => 'required|in:M,F',
        ]);

        $request->merge(['students' => $validated['rows']]);

        return $this->upsert($request, $classRecord, $q);
    }

    private function normalizeSex(?string $sex): string
    {
        return match (strtoupper(trim((string) $sex))) {
            'F', 'FEMALE' => 'F',
            default => 'M',
        };
    }

    private function normalizedName(?string $name): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $name)));
    }
}
