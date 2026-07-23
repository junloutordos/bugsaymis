<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Mail\ClassRecord\ClassRecordCheckedMail;
use App\Models\ClassRecord\ClassRecord;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ClassRecordController extends Controller
{
    private function isAdmin(): bool
    {
        return Auth::user()->hasPermission('class-records.admin');
    }

    // ── GET /class-records/my-teaching-load ──────────────────────────────────

    public function myTeachingLoad(): JsonResponse
    {
        $currentSY = SchoolYear::where('is_current', true)->first(['id', 'name']);

        if (! $currentSY) {
            return response()->json(['assignments' => [], 'school_year' => null]);
        }

        $isAdmin = $this->isAdmin();

        $query = LoadAssignment::with([
            'subject:id,name,subject_type,grade_level',
            'section:id,levelid,sectionname',
            'faculty:id,name',
        ])
            ->where('school_year_id', $currentSY->id)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id');

        if (! $isAdmin) {
            $query->where('user_id', Auth::id());
        }

        // A faculty can legitimately hold the same subject+section across both
        // terms of one school year (load_assignments' uniqueness is scoped per
        // term) — ClassRecord itself has no term concept, so collapse those
        // into the one card a teacher should see per subject+section.
        $assignments = $query->get()
            ->unique(fn ($la) => "{$la->user_id}_{$la->subject_id}_{$la->section_id}");

        // Flag assignments that already have a class record this SY
        $existingQuery = ClassRecord::where('school_year_id', $currentSY->id)
            ->whereNotNull('subject_id');

        if (! $isAdmin) {
            $existingQuery->where('teacher_id', Auth::id());
        }

        $existingPairs = $existingQuery
            ->get(['teacher_id', 'subject_id', 'section_id'])
            ->map(fn ($r) => $r->teacher_id.'_'.$r->subject_id.'_'.$r->section_id)
            ->flip();

        $result = $assignments->map(function ($la) use ($existingPairs) {
            $isCrossSection = in_array($la->subject?->subject_type, ['elective', 'science_core'], true);
            $grade = (int) ($la->subject?->grade_level ?? 0);
            $scopeLabel = $la->section?->sectionname
                ?? ($grade === 0 ? 'Grades 11–12 — Cross-section' : "Grade {$grade} — Cross-section");

            return [
                'load_assignment_id' => $la->id,
                'teacher_id' => $la->user_id,
                'teacher_name' => $la->faculty?->name,
                'subject_id' => $la->subject_id,
                'subject_name' => $la->subject?->name,
                'subject_type' => $la->subject?->subject_type,
                'grade_level' => $grade,
                'section_id' => $la->section_id,
                'section_name' => $la->section?->sectionname,
                'scope_label' => $scopeLabel,
                'is_cross_section' => $isCrossSection,
                'display_label' => ($la->subject?->name ?? '—').' — '.$scopeLabel,
                'already_created' => $existingPairs->has($la->user_id.'_'.$la->subject_id.'_'.$la->section_id),
            ];
        });

        return response()->json([
            'assignments' => $result,
            'school_year' => $currentSY->name,
        ]);
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
        $currentSY = SchoolYear::where('is_current', true)->first();

        if (! $currentSY) {
            return response()->json([
                'message' => 'No active school year is set. Please contact the administrator.',
                'errors' => ['school_year' => ['No active school year is configured.']],
            ], 422);
        }

        $validated = $request->validate([
            'subject_id' => 'required|integer|exists:subjects,id',
            'section_id' => 'nullable|integer|exists:sections,id',
            'grading_option_id' => 'required|integer|exists:grading_options,id',
            // Admins may create on behalf of another teacher
            'teacher_id' => 'nullable|integer|exists:users,id',
        ]);

        $subject = Subject::findOrFail($validated['subject_id']);
        $isCrossSection = in_array($subject->subject_type, ['elective', 'science_core'], true);

        if (! $isCrossSection && empty($validated['section_id'])) {
            return response()->json([
                'message' => 'A regular subject must be linked to a section.',
                'errors' => ['section_id' => ['Select a section for this regular subject.']],
            ], 422);
        }

        $section = null;
        if (! empty($validated['section_id'])) {
            // Verify section belongs to the current school year
            $section = Section::where('id', $validated['section_id'])
                ->where('school_year_id', $currentSY->id)
                ->first();

            if (! $section) {
                return response()->json([
                    'message' => 'The selected section does not belong to the current school year.',
                    'errors' => ['section_id' => ['Invalid section for current school year.']],
                ], 422);
            }
        }

        // Auto-derive display fields from FK data
        $subjectName = $subject->name;
        if ($section) {
            $yearLevelSection = "G-{$section->levelid} {$section->sectionname}";
        } elseif ((int) $subject->grade_level === 0) {
            $yearLevelSection = 'Grades 11–12 — Elective';
        } else {
            $scopeName = $subject->subject_type === 'science_core' ? 'Science Core' : 'Elective';
            $yearLevelSection = "Grade {$subject->grade_level} — {$scopeName}";
        }

        // Non-admins can only create for themselves
        $teacherId = ($this->isAdmin() && isset($validated['teacher_id']))
            ? $validated['teacher_id']
            : Auth::id();

        $record = ClassRecord::create([
            'subject_id' => $validated['subject_id'],
            'section_id' => $validated['section_id'] ?? null,
            'grading_option_id' => $validated['grading_option_id'],
            'subject_name' => $subjectName,
            'year_level_section' => $yearLevelSection,
            'teacher_id' => $teacherId,
            'school_year_id' => $currentSY->id,
            'school_year' => $currentSY->name,
            'status' => 'draft',
        ]);

        return response()->json([
            'message' => 'Class record created successfully.',
            'data' => $record->load('gradingOption'),
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
        $classRecord->setAttribute('can_change_grading_option', $classRecord->canChangeGradingOption($this->isAdmin()));

        return response()->json($classRecord);
    }

    // ── PUT /class-records/{classRecord} ──────────────────────────────────────

    public function update(Request $request, ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record belongs to a past school year and cannot be modified.');
        abort_if($classRecord->status === 'submitted' && ! $this->isAdmin(), 403,
            'Cannot edit a submitted class record.');

        $validated = $request->validate([
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'section_id' => 'nullable|integer',
            'grading_option_id' => 'sometimes|integer|exists:grading_options,id',
            'school_year' => 'sometimes|string|max:20',
            'subject_name' => 'sometimes|string|max:255',
            'year_level_section' => 'sometimes|string|max:255',
        ]);

        // Block grading option change once assessments or scores already exist under it
        if (isset($validated['grading_option_id'])
            && $validated['grading_option_id'] != $classRecord->grading_option_id) {
            if ($classRecord->hasScores()) {
                $message = 'Cannot change grading option after scores have been entered.';
            } elseif ($classRecord->hasAssessments()) {
                $message = 'Cannot change grading option while assessments already exist for this record. Remove them first.';
            }

            if (isset($message)) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['grading_option_id' => [$message]],
                ], 422);
            }
        }

        $classRecord->update($validated);
        $classRecord->refresh()->load('gradingOption.categories');
        $classRecord->setAttribute('can_change_grading_option', $classRecord->canChangeGradingOption($this->isAdmin()));

        return response()->json([
            'message' => 'Class record updated.',
            'data' => $classRecord,
        ]);
    }

    // ── DELETE /class-records/{classRecord} ───────────────────────────────────

    public function destroy(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record belongs to a past school year and cannot be modified.');
        abort_if($classRecord->status !== 'draft', 422,
            'Only draft class records can be deleted.');

        $classRecord->delete();

        return response()->json(['message' => 'Class record deleted.']);
    }

    // ── POST /class-records/{classRecord}/submit ──────────────────────────────

    public function submit(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin() || $classRecord->teacher_id === Auth::id(), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year and is read-only.');
        abort_if($classRecord->status !== 'draft', 422, 'Only draft records can be submitted.');

        $classRecord->update([
            'status' => 'submitted',
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
            'status' => 'checked',
            'checked_at' => now(),
            'checked_by_id' => Auth::id(),
        ]);

        $classRecord->load('teacher');
        $checker = Auth::user();

        NotificationService::notifyUser(
            user: $classRecord->teacher,
            requestType: 'Class Record',
            referenceNo: "{$classRecord->subject_name} — {$classRecord->year_level_section}",
            newStatus: 'Checked',
            url: route('class-records.page.show', $classRecord),
        );

        Mail::to($classRecord->teacher)->queue(new ClassRecordCheckedMail($classRecord, $checker));

        return response()->json(['message' => 'Class record marked as checked.']);
    }
}
