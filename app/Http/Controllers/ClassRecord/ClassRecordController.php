<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Mail\ClassRecord\ClassRecordCheckedMail;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordTeacher;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\ClassRecord\GradingOptionScopeService;
use App\Services\DigitalSignatureService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ClassRecordController extends Controller
{
    public function __construct(
        private readonly GradingOptionScopeService $optionScope,
        private readonly DigitalSignatureService $signatures,
    ) {
    }

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
            'subject:id,name,subject_type,grade_level,academic_unit_id',
            'subject.academicUnit:id,code,name',
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

        // Flag assignments that already have a class record this SY, and surface
        // the existing record(s) + their category labels so the wizard can offer
        // "add another category" instead of just blocking re-creation.
        $existingQuery = ClassRecord::where('school_year_id', $currentSY->id)
            ->where('status', '<>', 'archived')
            ->whereNotNull('subject_id');

        if (! $isAdmin) {
            $existingQuery->where('teacher_id', Auth::id());
        }

        $existingByPair = $existingQuery
            ->get(['id', 'teacher_id', 'subject_id', 'section_id', 'category_label'])
            ->groupBy(fn ($r) => $r->teacher_id.'_'.$r->subject_id.'_'.$r->section_id);

        // For subjects in a shared group (PE/Health/Music), a record already
        // exists for this section+SY+group even if it was created under a
        // DIFFERENT sibling subject_id (e.g. the PE teacher already made the
        // shared record; the Music teacher's own subject_id won't match it).
        // These are surfaced separately as "joinable" — the assignment is
        // still "creatable" from this teacher's own perspective, just via
        // joining rather than a fresh POST /class-records.
        $groupSubjectIds = Subject::whereNotNull('subject_group')->pluck('subject_group', 'id');
        $joinableQuery = ClassRecord::where('school_year_id', $currentSY->id)
            ->where('status', '<>', 'archived')
            ->whereNotNull('subject_id');
        $joinableByGroupSection = $joinableQuery
            ->get(['id', 'subject_id', 'section_id', 'subject_name', 'year_level_section'])
            ->filter(fn ($r) => $groupSubjectIds->has($r->subject_id))
            ->groupBy(fn ($r) => $groupSubjectIds[$r->subject_id].'_'.$r->section_id);

        $result = $assignments->map(function ($la) use ($existingByPair, $groupSubjectIds, $joinableByGroupSection) {
            $isCrossSection = in_array($la->subject?->subject_type, ['elective', 'science_core'], true);
            $grade = (int) ($la->subject?->grade_level ?? 0);
            $scopeLabel = $la->section?->sectionname
                ?? ($grade === 0 ? 'Grades 11–12 — Cross-section' : "Grade {$grade} — Cross-section");
            $existingRecords = $existingByPair->get($la->user_id.'_'.$la->subject_id.'_'.$la->section_id, collect());

            // Shared records this teacher could JOIN instead of creating a new
            // one — only relevant for a subject in a shared group, and only
            // records NOT already created under this exact subject_id (those
            // are already covered by existing_records above).
            $subjectGroup = $groupSubjectIds->get($la->subject_id);
            $joinable = collect();
            if ($subjectGroup !== null) {
                $joinable = $joinableByGroupSection
                    ->get($subjectGroup.'_'.$la->section_id, collect())
                    ->where('subject_id', '!=', $la->subject_id)
                    ->values();
            }

            return [
                'load_assignment_id' => $la->id,
                'teacher_id' => $la->user_id,
                'teacher_name' => $la->faculty?->name,
                'subject_id' => $la->subject_id,
                'subject_name' => $la->subject?->name,
                'subject_type' => $la->subject?->subject_type,
                'subject_group' => $subjectGroup,
                'academic_unit_id' => $la->subject?->academic_unit_id,
                'academic_unit_code' => $la->subject?->academicUnit?->code,
                'grade_level' => $grade,
                'section_id' => $la->section_id,
                'section_name' => $la->section?->sectionname,
                'scope_label' => $scopeLabel,
                'is_cross_section' => $isCrossSection,
                'display_label' => ($la->subject?->name ?? '—').' — '.$scopeLabel,
                'already_created' => $existingRecords->isNotEmpty(),
                'existing_records' => $existingRecords->map(fn ($r) => [
                    'id' => $r->id,
                    'category_label' => $r->category_label,
                ])->values(),
                'joinable_records' => $joinable->map(fn ($r) => [
                    'id' => $r->id,
                    'subject_name' => $r->subject_name,
                    'year_level_section' => $r->year_level_section,
                ])->values(),
            ];
        });

        return response()->json([
            'assignments' => $result,
            'school_year' => $currentSY->name,
        ]);
    }

    // ── POST /class-records/{classRecord}/join ────────────────────────────────
    // Attaches the caller (or, for an admin, a specified teacher) as a
    // co-teacher of an existing shared class record for their own subject —
    // used when a PE/Health/Music teacher's subject already has a shared
    // record on this section (created by one of the other two teachers),
    // instead of creating a duplicate class_records row.

    public function join(Request $request, ClassRecord $classRecord): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => 'required|integer|exists:subjects,id',
            // Admins may join a teacher on another's behalf.
            'teacher_id' => 'nullable|integer|exists:users,id',
        ]);

        $isAdmin = $this->isAdmin();
        $teacherId = ($isAdmin && ! empty($validated['teacher_id'])) ? (int) $validated['teacher_id'] : Auth::id();

        abort_if($classRecord->isArchived(), 422, 'This class record has been archived.');
        abort_unless($classRecord->isCurrentSchoolYear(), 403, 'This class record is from a past school year.');

        $subject = Subject::findOrFail($validated['subject_id']);
        abort_unless($subject->subject_group !== null, 422, 'This subject does not support shared class records.');
        abort_unless(
            $subject->subject_group === Subject::find($classRecord->subject_id)?->subject_group,
            422,
            'That subject is not part of the same shared group as this class record.',
        );
        abort_unless(
            $subject->grade_level === Subject::find($classRecord->subject_id)?->grade_level,
            422,
            'That subject is a different grade level than this class record.',
        );

        // A teacher may not join twice under the same subject, and a subject
        // slot on a shared record can only be held by one teacher at a time
        // (joining again re-assigns it rather than duplicating the row).
        $existing = $classRecord->coTeachers()->where('subject_id', $subject->id)->first();
        if ($existing && (int) $existing->user_id === $teacherId) {
            return response()->json([
                'message' => 'You are already a teacher on this shared class record for that subject.',
                'data' => $classRecord->fresh()->load('coTeachers.subject:id,name', 'coTeachers.user:id,name'),
            ]);
        }
        abort_if(
            $existing && ! $isAdmin,
            422,
            "Another teacher already owns {$subject->name} on this shared class record.",
        );

        DB::transaction(function () use ($classRecord, $subject, $teacherId) {
            // Ensure the primary teacher_id also has a pivot row for its own
            // subject the first time anyone joins — otherwise allTeacherIds()/
            // teacherIdsFor() still work via the teacher_id fallback, but
            // having an explicit row keeps coTeachers a complete roster once
            // the record becomes genuinely shared.
            if (! $classRecord->coTeachers()->where('user_id', $classRecord->teacher_id)->exists()) {
                $primarySubjectId = $classRecord->subject_id;
                if ($primarySubjectId) {
                    ClassRecordTeacher::create([
                        'class_record_id' => $classRecord->id,
                        'subject_id' => $primarySubjectId,
                        'user_id' => $classRecord->teacher_id,
                        'is_primary' => true,
                    ]);
                }
            }

            ClassRecordTeacher::updateOrCreate(
                ['class_record_id' => $classRecord->id, 'subject_id' => $subject->id],
                ['user_id' => $teacherId, 'is_primary' => false],
            );

            $classRecord->refresh();
            $this->syncSharedDisplayLabel($classRecord);
        });

        return response()->json([
            'message' => "Joined this shared class record as the {$subject->name} teacher.",
            'data' => $classRecord->fresh()->load('coTeachers.subject:id,name', 'coTeachers.user:id,name'),
        ]);
    }

    // ── GET /class-records/{classRecord}/co-teacher-candidates ────────────────
    // Admin-only lookup used by the "Add Co-teacher" control on the Show page:
    // which sibling subjects (same subject_group + grade_level) still have no
    // teacher on this record, and which teachers are actually assigned (via
    // LoadAssignment) to teach that subject on this section this school year.

    public function coTeacherCandidates(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);

        $primarySubject = Subject::find($classRecord->subject_id);
        $currentSY = SchoolYear::where('is_current', true)->first(['id']);

        if (! $primarySubject?->subject_group || ! $currentSY) {
            return response()->json(['subjects' => [], 'teachers_by_subject' => (object) []]);
        }

        $coveredSubjectIds = $classRecord->coTeachers()->pluck('subject_id');

        $siblings = Subject::where('subject_group', $primarySubject->subject_group)
            ->where('grade_level', $primarySubject->grade_level)
            ->where('id', '!=', $classRecord->subject_id)
            ->whereNotIn('id', $coveredSubjectIds)
            ->get(['id', 'name']);

        $teachersBySubject = LoadAssignment::whereIn('subject_id', $siblings->pluck('id'))
            ->where('section_id', $classRecord->section_id)
            ->where('school_year_id', $currentSY->id)
            ->where('assignment_type', 'teaching')
            ->with('faculty:id,name')
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('faculty')->filter()->unique('id')->values());

        return response()->json([
            'subjects' => $siblings,
            'teachers_by_subject' => $teachersBySubject,
        ]);
    }

    /**
     * Once a record has co-teachers, its subject_name becomes a synthesized
     * "group — subject / subject / subject" label (e.g. "PEHM — PE / Health
     * / Music") reflecting every subject currently attached, instead of just
     * the creator's own subject name. subject_id itself is left pointing at
     * the original creator's subject for legacy-field compatibility —
     * anything reading ->subject_id/->subject directly still gets a valid
     * single subject; category_label is untouched (it means something else:
     * splitting ONE subject's records, e.g. "Ongoing" vs "Completed").
     */
    private function syncSharedDisplayLabel(ClassRecord $classRecord): void
    {
        $classRecord->loadMissing('coTeachers.subject:id,name,subject_group');
        $subjectIds = $classRecord->coTeachers->pluck('subject_id')->unique();
        if ($subjectIds->count() < 2) {
            return;
        }

        $group = $classRecord->coTeachers->first()->subject?->subject_group ?? 'Shared';
        $names = $classRecord->coTeachers
            ->sortBy('subject_id')
            ->pluck('subject.name')
            ->filter()
            ->unique()
            ->values()
            ->implode(' / ');

        $classRecord->update(['subject_name' => "{$group} — {$names}"]);
    }

    // ── GET /class-records ────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = ClassRecord::with(['teacher:id,name', 'gradingOption:id,name'])
            ->orderByDesc('created_at');

        if (! $this->isAdmin()) {
            $userId = Auth::id();
            $query->where(function ($q) use ($userId) {
                $q->where('teacher_id', $userId)
                    ->orWhereHas('coTeachers', fn ($ctq) => $ctq->where('user_id', $userId));
            });
        }

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->query('school_year'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            // Default listing hides archived (soft-deleted) records.
            $query->where('status', '<>', 'archived');
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
            'category_label' => 'nullable|string|max:100',
            // Admins may create on behalf of another teacher
            'teacher_id' => 'nullable|integer|exists:users,id',
        ]);

        $subject = Subject::findOrFail($validated['subject_id']);
        $gradingOption = GradingOption::findOrFail($validated['grading_option_id']);
        if (! $this->optionScope->isSelectableForSubject($gradingOption, $subject, Auth::user())) {
            return response()->json([
                'message' => 'That grading option is inactive or belongs to another academic unit.',
                'errors' => ['grading_option_id' => ['Select an active grading option available to your unit.']],
            ], 422);
        }
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

        $categoryLabel = trim((string) ($validated['category_label'] ?? '')) ?: null;
        $labelError = $this->checkCategoryLabel(
            $subjectId = (int) $validated['subject_id'],
            $sectionId = $validated['section_id'] ?? null,
            $teacherId,
            $currentSY->id,
            $categoryLabel,
        );
        if ($labelError) {
            return $labelError;
        }

        $record = ClassRecord::create([
            'subject_id' => $validated['subject_id'],
            'section_id' => $validated['section_id'] ?? null,
            'grading_option_id' => $validated['grading_option_id'],
            'subject_name' => $subjectName,
            'category_label' => $categoryLabel,
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

    /**
     * A subject can be split into multiple category-labeled class records
     * (e.g. STEM Research "Ongoing" vs "Completed") that share the same
     * subject+section+teacher+SY. The first record for that tuple needs no
     * label; any additional one must carry a label, distinct from its
     * siblings', so the split is always deliberate and never ambiguous.
     */
    private function checkCategoryLabel(int $subjectId, ?int $sectionId, int $teacherId, int $schoolYearId, ?string $categoryLabel, ?int $excludeId = null): ?JsonResponse
    {
        $error = $this->categoryLabelConflict($subjectId, $sectionId, $teacherId, $schoolYearId, $categoryLabel, $excludeId);
        if (! $error) {
            return null;
        }

        return response()->json([
            'message' => $error,
            'errors' => ['category_label' => [$error]],
        ], 422);
    }

    /** Same rule as checkCategoryLabel(), but returns a plain reason string for batch skip-lists. */
    private function categoryLabelConflict(int $subjectId, ?int $sectionId, int $teacherId, int $schoolYearId, ?string $categoryLabel, ?int $excludeId = null): ?string
    {
        $siblings = ClassRecord::where('subject_id', $subjectId)
            ->where('teacher_id', $teacherId)
            ->where('school_year_id', $schoolYearId)
            ->where('status', '<>', 'archived')
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->when(
                $sectionId === null,
                fn ($q) => $q->whereNull('section_id'),
                fn ($q) => $q->where('section_id', $sectionId),
            )
            ->get(['id', 'category_label']);

        if ($siblings->isEmpty()) {
            return null;
        }

        if ($categoryLabel === null) {
            return 'A class record already exists for this subject. Give this one a category label (e.g. "Ongoing", "Completed") to create another.';
        }

        $normalized = mb_strtolower($categoryLabel);
        $collides = $siblings->contains(fn ($s) => $s->category_label !== null && mb_strtolower($s->category_label) === $normalized);

        if ($collides) {
            return "Another class record for this subject already uses the category label \"{$categoryLabel}\".";
        }

        return null;
    }

    // ── POST /class-records/bulk ────────────────────────────────────────────────
    // Creates one class record per selected teaching assignment, all sharing the
    // same grading option. Used when a teacher selects multiple subjects in
    // Step 1 of the creation wizard. Items that already have a class record for
    // the current SY are silently skipped (not treated as a failure) so the
    // rest of the batch still succeeds.

    public function bulkStore(Request $request): JsonResponse
    {
        $currentSY = SchoolYear::where('is_current', true)->first();

        if (! $currentSY) {
            return response()->json([
                'message' => 'No active school year is set. Please contact the administrator.',
                'errors' => ['school_year' => ['No active school year is configured.']],
            ], 422);
        }

        $validated = $request->validate([
            'grading_option_id' => 'required|integer|exists:grading_options,id',
            'items' => 'required|array|min:1',
            'items.*.subject_id' => 'required|integer|exists:subjects,id',
            'items.*.section_id' => 'nullable|integer|exists:sections,id',
            'items.*.teacher_id' => 'nullable|integer|exists:users,id',
            'items.*.category_label' => 'nullable|string|max:100',
        ]);

        $gradingOption = GradingOption::findOrFail($validated['grading_option_id']);
        $isAdmin = $this->isAdmin();

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($validated, $gradingOption, $currentSY, $isAdmin, &$created, &$skipped) {
            foreach ($validated['items'] as $item) {
                $subject = Subject::find($item['subject_id']);
                if (! $subject) {
                    $skipped[] = ['subject_id' => $item['subject_id'], 'reason' => 'Subject not found.'];

                    continue;
                }

                if (! $this->optionScope->isSelectableForSubject($gradingOption, $subject, Auth::user())) {
                    $skipped[] = ['subject_id' => $item['subject_id'], 'reason' => 'Grading option not applicable to this subject.'];

                    continue;
                }

                $isCrossSection = in_array($subject->subject_type, ['elective', 'science_core'], true);

                if (! $isCrossSection && empty($item['section_id'])) {
                    $skipped[] = ['subject_id' => $item['subject_id'], 'reason' => 'A regular subject must be linked to a section.'];

                    continue;
                }

                $section = null;
                if (! empty($item['section_id'])) {
                    $section = Section::where('id', $item['section_id'])
                        ->where('school_year_id', $currentSY->id)
                        ->first();

                    if (! $section) {
                        $skipped[] = ['subject_id' => $item['subject_id'], 'reason' => 'Section does not belong to the current school year.'];

                        continue;
                    }
                }

                $teacherId = ($isAdmin && ! empty($item['teacher_id'])) ? $item['teacher_id'] : Auth::id();
                $categoryLabel = trim((string) ($item['category_label'] ?? '')) ?: null;

                // Skip pairs that already have a non-archived class record this SY,
                // unless this item carries a distinct category label — that's a
                // deliberate "split this subject" request, not a duplicate.
                $labelConflict = $this->categoryLabelConflict(
                    $subject->id, $item['section_id'] ?? null, $teacherId, $currentSY->id, $categoryLabel,
                );

                if ($labelConflict) {
                    $skipped[] = ['subject_id' => $item['subject_id'], 'reason' => $labelConflict];

                    continue;
                }

                $subjectName = $subject->name;
                if ($section) {
                    $yearLevelSection = "G-{$section->levelid} {$section->sectionname}";
                } elseif ((int) $subject->grade_level === 0) {
                    $yearLevelSection = 'Grades 11–12 — Elective';
                } else {
                    $scopeName = $subject->subject_type === 'science_core' ? 'Science Core' : 'Elective';
                    $yearLevelSection = "Grade {$subject->grade_level} — {$scopeName}";
                }

                $record = ClassRecord::create([
                    'subject_id' => $subject->id,
                    'section_id' => $item['section_id'] ?? null,
                    'grading_option_id' => $gradingOption->id,
                    'subject_name' => $subjectName,
                    'category_label' => $categoryLabel,
                    'year_level_section' => $yearLevelSection,
                    'teacher_id' => $teacherId,
                    'school_year_id' => $currentSY->id,
                    'school_year' => $currentSY->name,
                    'status' => 'draft',
                ]);

                $created[] = $record;
            }
        });

        if (! $created) {
            return response()->json([
                'message' => 'No class records were created. See the skipped reasons below.',
                'created' => [],
                'skipped' => $skipped,
            ], 422);
        }

        return response()->json([
            'message' => count($created).' class record(s) created successfully.'.
                ($skipped ? ' '.count($skipped).' skipped.' : ''),
            'created' => $created,
            'skipped' => $skipped,
        ], 201);
    }

    // ── GET /class-records/{classRecord} ──────────────────────────────────────

    public function show(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);

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
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isCurrentSchoolYear(), 403, 'This class record belongs to a past school year and cannot be modified.');
        abort_if($classRecord->status === 'submitted' && ! $this->isAdmin(), 403,
            'Cannot edit a submitted class record.');

        $validated = $request->validate([
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'section_id' => 'nullable|integer',
            'grading_option_id' => 'sometimes|integer|exists:grading_options,id',
            'school_year' => 'sometimes|string|max:20',
            'subject_name' => 'sometimes|string|max:255',
            'category_label' => 'sometimes|nullable|string|max:100',
            'year_level_section' => 'sometimes|string|max:255',
        ]);

        if (array_key_exists('category_label', $validated)) {
            $validated['category_label'] = trim((string) ($validated['category_label'] ?? '')) ?: null;
            $labelError = $this->checkCategoryLabel(
                $classRecord->subject_id,
                $classRecord->section_id,
                $classRecord->teacher_id,
                $classRecord->school_year_id,
                $validated['category_label'],
                $classRecord->id,
            );
            if ($labelError) {
                return $labelError;
            }
        }

        if (isset($validated['grading_option_id'])) {
            $subject = isset($validated['subject_id'])
                ? Subject::find($validated['subject_id'])
                : $classRecord->subject;
            $gradingOption = GradingOption::find($validated['grading_option_id']);

            if (! $subject || ! $gradingOption || ! $this->optionScope->isSelectableForSubject($gradingOption, $subject, Auth::user())) {
                return response()->json([
                    'message' => 'That grading option is inactive or belongs to another academic unit.',
                    'errors' => ['grading_option_id' => ['Select an active grading option available to your unit.']],
                ], 422);
            }
        }

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

    // ── DELETE /class-records/{classRecord} — archive (reversible soft-delete) ─

    public function destroy(Request $request, ClassRecord $classRecord): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if($classRecord->isArchived(), 422, 'This class record is already archived.');

        $user = Auth::user();
        if (empty($user->signature_pin)) {
            return response()->json([
                'message' => 'Set up your signature PIN in your profile before archiving a class record.',
                'errors' => ['pin' => ['No signature PIN is set for your account.']],
            ], 422);
        }

        $validated = $request->validate(['pin' => 'required|string']);
        if (! $this->signatures->verifyPin($user, $validated['pin'])) {
            return response()->json([
                'message' => 'The signature PIN is incorrect.',
                'errors' => ['pin' => ['The signature PIN is incorrect.']],
            ], 422);
        }

        $classRecord->update([
            'pre_archive_status' => $classRecord->status,
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by_id' => $user->id,
        ]);

        return response()->json(['message' => 'Class record archived. It can be restored from the Archived filter.']);
    }

    // ── POST /class-records/{classRecord}/restore ─────────────────────────────

    public function restore(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
        abort_if(! $classRecord->isArchived(), 422, 'Only archived class records can be restored.');

        $classRecord->update([
            'status' => $classRecord->pre_archive_status ?: 'draft',
            'archived_at' => null,
            'archived_by_id' => null,
            'pre_archive_status' => null,
        ]);

        return response()->json(['message' => 'Class record restored.']);
    }

    // ── POST /class-records/{classRecord}/submit ──────────────────────────────

    public function submit(ClassRecord $classRecord): JsonResponse
    {
        abort_unless($classRecord->canEdit(Auth::user()), 403);
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

        $classRecord->load('teacher', 'coTeachers.user');
        $checker = Auth::user();

        // On a shared (e.g. PEHM) record every co-teacher gets notified, not
        // just the primary teacher_id — each of them owns part of what was
        // just checked. allTeacherIds() already de-dupes and falls back to
        // just [teacher_id] for a normal single-teacher record.
        $recipients = User::whereIn('id', $classRecord->allTeacherIds())->get();

        foreach ($recipients as $recipient) {
            NotificationService::notifyUser(
                user: $recipient,
                requestType: 'Class Record',
                referenceNo: "{$classRecord->subject_name} — {$classRecord->year_level_section}",
                newStatus: 'Checked',
                url: route('class-records.page.show', $classRecord),
            );

            Mail::to($recipient)->queue(new ClassRecordCheckedMail($classRecord, $checker, $recipient));
        }

        return response()->json(['message' => 'Class record marked as checked.']);
    }
}
