<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\HeadAdvisoryService;
use App\Services\FacultyLoading\ScheduleBlockAvailabilityService;
use App\Services\FacultyLoading\ClassScheduleScopeLockService;
use App\Services\FacultyLoading\SchedulingConstants;
use App\Services\FacultyLoading\ScienceCoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function __construct(private readonly ClassScheduleScopeLockService $scopeLocks) {}

    // ── List sections ─────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        // Default to the current FL school year; always filter by the FL-specific column
        // (school_year_id) so legacy sections that have a null school_year_id are excluded.
        $currentId    = SchoolYear::where('is_current', true)->value('id');
        $schoolYearId = (int) $request->input('school_year_id', $currentId);

        $sections = Section::with(['flSchoolYear', 'adviserUser:id,name', 'classroom:id,name,code'])
            ->where('school_year_id', $schoolYearId)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get()
            ->map(fn ($s) => $this->mapSection($s));

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);
        $faculty     = User::employees()->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name']);
        $classrooms  = Classroom::orderBy('name')->get(['id', 'name', 'code', 'classroom_type']);

        return Inertia::render('FacultyLoading/Sections/Index', [
            'sections'    => $sections,
            'schoolYears' => $schoolYears,
            'faculty'     => $faculty,
            'classrooms'  => $classrooms,
            'filters'     => ['school_year_id' => $schoolYearId],
        ]);
    }

    // ── View section detail ───────────────────────────────────────────────────

    public function show(Request $request, Section $section, ScienceCoreService $scienceCore): Response
    {
        $this->authorize('faculty_loading.manage');
        $section->load('consultationOverrides');

        // Academic terms for this section's school year
        $terms = AcademicTerm::where('school_year_id', $section->school_year_id)
            ->orderBy('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $currentTermId = AcademicTerm::where('is_current', true)->value('id');
        $termId        = (int) $request->input('term_id', $currentTermId);

        // All active catalog subjects for this grade level.
        // Cross-grade electives (grade_level=0) are only offered in Grades 10–12.
        $catalogSubjects = Subject::where('is_active', true)
            ->where(function ($q) use ($section) {
                $q->where('grade_level', $section->levelid);
                if ($section->levelid >= 10) {
                    $q->orWhere('grade_level', 0);
                }
            })
            ->orderBy('grade_level')
            ->orderBy('code')
            ->get();

        // Existing assignments for this section in the selected term, keyed by subject_id
        $assignmentsBySubject = LoadAssignment::with('faculty:id,name')
            ->where('section_id', $section->id)
            ->where('assignment_type', 'teaching')
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->get()
            ->keyBy('subject_id');

        // Merge catalog + assignments — every catalog subject appears; assigned ones show the faculty
        $subjects = $catalogSubjects->map(function ($s) use ($assignmentsBySubject) {
            $assignment = $assignmentsBySubject->get($s->id);

            return [
                'id'            => $s->id,
                'subject_code'  => $s->code,
                'subject_name'  => $s->name,
                'subject_type'  => $s->subject_type,
                'load_units'    => (float) $s->load_units,
                'grade_level'   => $s->grade_level,
                'is_elective'   => $s->grade_level === 0,
                'assignment_id' => $assignment?->id,
                'faculty_id'    => $assignment?->user_id,
                'faculty_name'  => $assignment?->faculty?->name,
                'is_assigned'   => $assignment !== null,
            ];
        })->values();

        // Students in this section via section_students pivot
        $students = DB::table('section_students as ss')
            ->join('students as st', 'st.id', '=', 'ss.studentid')
            ->where('ss.sectionid', $section->id)
            ->where('ss.syid', $section->syid)
            ->orderBy('st.lastname')
            ->orderBy('st.firstname')
            ->get(['st.id', 'st.lastname', 'st.firstname', 'st.middlename',
                   'st.pisaysystemID', 'st.lrn', 'st.sex', 'st.student_email', 'st.status'])
            ->map(fn ($s) => [
                'id'        => $s->id,
                'full_name' => trim("{$s->lastname}, {$s->firstname}" . ($s->middlename ? " {$s->middlename}" : '')),
                'system_id' => $s->pisaysystemID,
                'lrn'       => $s->lrn,
                'sex'       => $s->sex,
                'email'     => $s->student_email,
                'status'    => $s->status,
            ]);

        $section->loadMissing(['flSchoolYear', 'adviserUser', 'classroom:id,name,code']);

        // This section's own weekly schedule — read-only render, so no locked-
        // faculty list or capability is passed (toCalendarArray() defaults to
        // is_locked=false, can_edit=false, which is exactly what a display-only
        // card needs).
        $schedule = ClassSchedule::with(['subject', 'classroom', 'faculty:id,name', 'section:id,sectionname,levelid'])
            ->where('section_id', $section->id)
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->occupying()
            ->get()
            ->map(fn ($s) => $s->toCalendarArray());

        // The elective/science-core bands mark released windows on a homeroom
        // section; a synthetic elective (ELEC-*) or Science Core (SCI-*)
        // section already shows its real classes there, so it gets no band.
        $isElectiveSection    = str_starts_with((string) $section->sectionname, 'ELEC-');
        $isScienceCoreSection = $scienceCore->isScienceCoreSection($section);
        $dayConfigs = [];
        foreach (SchedulingConstants::DAYS as $day) {
            $window = SchedulingConstants::getEffectiveClassWindow($section->levelid, $day);
            $dayConfigs[$day] = [
                'start'        => $window['start'] ?? null,
                'end'          => $window['end'] ?? null,
                'blocked' => SchedulingConstants::getDisplayBlockedSlots(
                    $section->levelid,
                    $day,
                    $section->lunchOverrideFor($day),
                    $section->recessOverrideFor($day),
                    $section->whiteSpaceOverrideFor($day),
                    $section->wellnessOverrideFor($day),
                    $section->consultationOverrideFor($day),
                ),
                'electives'    => $isElectiveSection ? [] : SchedulingConstants::getElectiveWindows($section->levelid, $day),
                'scienceCore'  => ($isElectiveSection || $isScienceCoreSection || ! $termId) ? [] : $scienceCore->getScienceCoreWindows($section->school_year_id, $termId, $section->levelid, $day),
            ];
        }

        return Inertia::render('FacultyLoading/Sections/Show', [
            'section'    => $this->mapSection($section),
            'terms'      => $terms,
            'termId'     => $termId,
            'subjects'   => $subjects,
            'students'   => $students,
            'schedule'   => $schedule,
            'dayConfigs' => $dayConfigs,
        ]);
    }

    // ── Create a section ──────────────────────────────────────────────────────

    public function store(Request $request, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $request->merge($this->normaliseBreakTimes($request));

        $data = $request->validate([
            'sectionname'  => 'required|string|max:100',
            'levelid'      => 'required|integer|min:7|max:12',
            'section_code' => 'nullable|string|max:30',
            'capacity'     => 'nullable|integer|min:1|max:60',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'recess_start' => 'nullable|date_format:H:i',
            'recess_end'   => 'nullable|date_format:H:i|after:recess_start',
            'lunch_start'  => 'nullable|date_format:H:i',
            'lunch_end'    => 'nullable|date_format:H:i|after:lunch_start',
            'adviser'      => 'nullable|exists:users,id',
            'homeroom_coordinator_id' => 'nullable|exists:users,id',
            'syid'         => 'required|exists:school_years,id',
            'is_active'    => 'boolean',
        ]);

        // Unique section name within the same school year and grade level
        $exists = Section::where('syid', $data['syid'])
            ->where('levelid', $data['levelid'])
            ->where('sectionname', $data['sectionname'])
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'sectionname' => "A Grade {$data['levelid']} section named '{$data['sectionname']}' already exists for this school year.",
            ]);
        }

        // Persist to both legacy syid and the FL-specific school_year_id column
        $section = Section::create(array_merge($data, [
            'is_active'      => $data['is_active'] ?? true,
            'school_year_id' => $data['syid'],
        ]));

        // Auto-create the HRA-/HAC- designation for this section (grades 7–12)
        $advisory->ensureSectionDesignation($section);

        if ($section->adviser) {
            $advisory->syncSectionAdviser($section, null);
        }

        if ($section->homeroom_coordinator_id) {
            $advisory->syncHomeroomCoordinator($section, null);
        }

        return back()->with('success', 'Section created.');
    }

    // ── Update a section ──────────────────────────────────────────────────────

    public function update(Request $request, Section $section, HeadAdvisoryService $advisory): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $request->merge($this->normaliseBreakTimes($request));

        $data = $request->validate([
            'sectionname'  => 'required|string|max:100',
            'levelid'      => 'required|integer|min:7|max:12',
            'section_code' => 'nullable|string|max:30',
            'capacity'     => 'nullable|integer|min:1|max:60',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'recess_start' => 'nullable|date_format:H:i',
            'recess_end'   => 'nullable|date_format:H:i|after:recess_start',
            'lunch_start'  => 'nullable|date_format:H:i',
            'lunch_end'    => 'nullable|date_format:H:i|after:lunch_start',
            'adviser'      => 'nullable|exists:users,id',
            'homeroom_coordinator_id' => 'nullable|exists:users,id',
            'is_active'    => 'boolean',
        ]);

        // Unique check, excluding current record
        $exists = Section::where('syid', $section->syid)
            ->where('levelid', $data['levelid'])
            ->where('sectionname', $data['sectionname'])
            ->where('id', '!=', $section->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'sectionname' => "A Grade {$data['levelid']} section named '{$data['sectionname']}' already exists for this school year.",
            ]);
        }

        $oldAdviserId = $section->adviser ? (int) $section->adviser : null;
        $newAdviserId = isset($data['adviser']) && $data['adviser'] ? (int) $data['adviser'] : null;
        $oldCoordinatorId = $section->homeroom_coordinator_id ? (int) $section->homeroom_coordinator_id : null;
        $newCoordinatorId = isset($data['homeroom_coordinator_id']) && $data['homeroom_coordinator_id']
            ? (int) $data['homeroom_coordinator_id']
            : null;
        $oldName      = $section->sectionname;
        $oldCode      = $section->section_code ?? '';

        $section->update($data);

        // Sync the HRA-/HAC- designation name/code if section details changed
        $advisory->syncSectionDesignation($section->fresh(), $oldName, $oldCode);

        if ($oldAdviserId !== $newAdviserId) {
            $advisory->syncSectionAdviser($section->fresh(), $oldAdviserId);
        }

        if ($oldCoordinatorId !== $newCoordinatorId) {
            $advisory->syncHomeroomCoordinator($section->fresh(), $oldCoordinatorId);
        }

        return back()->with('success', 'Section updated.');
    }

    /**
     * Set (or clear) a single section's own lunch time for ONE weekday — used
     * by the calendar's per-section lunch popover so one section can move its
     * lunch on that day without touching the shared grade-wide bell schedule,
     * any other section, or its own lunch time on any other day. Falls back
     * to the section's regular lunch_start/lunch_end (and ultimately the
     * grade default) whenever these are left null.
     */
    public function updateDayLunch(Request $request, Section $section, string $day, ScheduleBlockAvailabilityService $availability): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        if (! array_key_exists($day, Section::LUNCH_OVERRIDE_COLUMNS)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$startField, $endField] = Section::LUNCH_OVERRIDE_COLUMNS[$day];

        $request->merge($this->normaliseBreakTimes($request, [$startField, $endField]));

        $data = $request->validate([
            $startField => 'nullable|date_format:H:i',
            $endField   => "nullable|date_format:H:i|after:{$startField}",
        ]);

        $startIsNull = ($data[$startField] ?? null) === null;
        $endIsNull   = ($data[$endField] ?? null) === null;
        if ($startIsNull !== $endIsNull) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [$endField => ['Provide both a start and end time, or leave both blank to clear the override.']],
            ], 422);
        }

        $window = $startIsNull
            ? SchedulingConstants::getEffectiveLunch((int) $section->levelid, $day)
            : ['start' => $data[$startField], 'end' => $data[$endField]];
        $termId = $this->assertSectionBlockAvailable($request, $section, $day, 'lunch', $window, $availability);
        $this->scopeLocks->runUnlocked([$termId => [(int) $section->id]], fn () => $section->update($data));

        return response()->json([
            'message'    => $data[$startField] ? "{$day} lunch updated for {$section->sectionname}." : "{$day} lunch override cleared for {$section->sectionname}.",
            $startField  => $section->$startField,
            $endField    => $section->$endField,
        ]);
    }

    /**
     * Set (or clear) a single section's own recess time for ONE weekday — used
     * by the calendar's per-section recess popover so one section can move its
     * recess on that day without touching the shared grade-wide bell schedule,
     * any other section, or its own recess time on any other day. Falls back
     * to the grade default (SchedulingConstants::getRecess) whenever left null.
     */
    public function updateDayRecess(Request $request, Section $section, string $day, ScheduleBlockAvailabilityService $availability): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        if (! array_key_exists($day, Section::RECESS_OVERRIDE_COLUMNS)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$startField, $endField] = Section::RECESS_OVERRIDE_COLUMNS[$day];

        $request->merge($this->normaliseBreakTimes($request, [$startField, $endField]));

        $data = $request->validate([
            $startField => 'nullable|date_format:H:i',
            $endField   => "nullable|date_format:H:i|after:{$startField}",
        ]);

        $startIsNull = ($data[$startField] ?? null) === null;
        $endIsNull   = ($data[$endField] ?? null) === null;
        if ($startIsNull !== $endIsNull) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [$endField => ['Provide both a start and end time, or leave both blank to clear the override.']],
            ], 422);
        }

        $window = $startIsNull
            ? (SchedulingConstants::getEffectiveRecess((int) $section->levelid, $day)[0] ?? null)
            : ['start' => $data[$startField], 'end' => $data[$endField]];
        $termId = $this->assertSectionBlockAvailable($request, $section, $day, 'recess', $window, $availability);
        $this->scopeLocks->runUnlocked([$termId => [(int) $section->id]], fn () => $section->update($data));

        return response()->json([
            'message'    => $data[$startField] ? "{$day} recess updated for {$section->sectionname}." : "{$day} recess override cleared for {$section->sectionname}.",
            $startField  => $section->$startField,
            $endField    => $section->$endField,
        ]);
    }

    /**
     * Set (or clear) a single section's own White Space time for ONE weekday
     * — the "this section only" scope of the White Space popover. Unlike
     * lunch/recess there is no grade default to fall back to: clearing this
     * removes the block entirely for this section on this day unless a
     * grade-wide or campus-wide White Space setting also applies (see
     * SchedulingConstants::getWhiteSpaceWindow()).
     */
    public function updateDayWhiteSpace(Request $request, Section $section, string $day, ScheduleBlockAvailabilityService $availability): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        if (! array_key_exists($day, Section::WHITE_SPACE_OVERRIDE_COLUMNS)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$startField, $endField] = Section::WHITE_SPACE_OVERRIDE_COLUMNS[$day];

        $request->merge($this->normaliseBreakTimes($request, [$startField, $endField]));

        $data = $request->validate([
            $startField => 'nullable|date_format:H:i',
            $endField   => "nullable|date_format:H:i|after:{$startField}",
        ]);

        $startIsNull = ($data[$startField] ?? null) === null;
        $endIsNull   = ($data[$endField] ?? null) === null;
        if ($startIsNull !== $endIsNull) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [$endField => ['Provide both a start and end time, or leave both blank to clear the override.']],
            ], 422);
        }

        $window = $startIsNull
            ? SchedulingConstants::getWhiteSpaceWindow((int) $section->levelid, $day)
            : ['start' => $data[$startField], 'end' => $data[$endField]];
        $termId = $this->assertSectionBlockAvailable($request, $section, $day, 'white_space', $window, $availability);
        $this->scopeLocks->runUnlocked([$termId => [(int) $section->id]], fn () => $section->update($data));

        return response()->json([
            'message'    => $data[$startField] ? "{$day} White Space updated for {$section->sectionname}." : "{$day} White Space cleared for {$section->sectionname}.",
            $startField  => $section->$startField,
            $endField    => $section->$endField,
        ]);
    }

    /**
     * Set (or clear) a single section's own Wellness time for ONE weekday —
     * mirrors updateDayWhiteSpace() exactly. Full-Wednesday grades still get
     * the structural Wednesday exemption regardless of this override (see
     * SchedulingConstants::resolveWellnessBlock()) — this endpoint doesn't
     * need to special-case that; it only ever writes the raw time.
     */
    public function updateDayWellness(Request $request, Section $section, string $day, ScheduleBlockAvailabilityService $availability): JsonResponse
    {
        $this->authorize('faculty_loading.manage');

        if (! array_key_exists($day, Section::WELLNESS_OVERRIDE_COLUMNS)) {
            return response()->json(['message' => 'Invalid day.'], 422);
        }

        [$startField, $endField] = Section::WELLNESS_OVERRIDE_COLUMNS[$day];

        $request->merge($this->normaliseBreakTimes($request, [$startField, $endField]));

        $data = $request->validate([
            $startField => 'nullable|date_format:H:i',
            $endField   => "nullable|date_format:H:i|after:{$startField}",
        ]);

        $startIsNull = ($data[$startField] ?? null) === null;
        $endIsNull   = ($data[$endField] ?? null) === null;
        if ($startIsNull !== $endIsNull) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => [$endField => ['Provide both a start and end time, or leave both blank to clear the override.']],
            ], 422);
        }

        $window = $startIsNull
            ? SchedulingConstants::getEffectiveWellnessWindow((int) $section->levelid, $day)
            : ['start' => $data[$startField], 'end' => $data[$endField]];
        $termId = $this->assertSectionBlockAvailable($request, $section, $day, 'wellness', $window, $availability);
        $this->scopeLocks->runUnlocked([$termId => [(int) $section->id]], fn () => $section->update($data));

        return response()->json([
            'message'    => $data[$startField] ? "{$day} Wellness updated for {$section->sectionname}." : "{$day} Wellness cleared for {$section->sectionname}.",
            $startField  => $section->$startField,
            $endField    => $section->$endField,
        ]);
    }

    // ── Delete a section ──────────────────────────────────────────────────────

    public function destroy(Section $section): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        // Soft-disable instead of hard-delete if schedules reference this section
        $hasSchedules = $section->classSchedules()->exists();

        if ($hasSchedules) {
            $section->update(['is_active' => false]);

            return back()->with('success', 'Section deactivated (has existing schedules).');
        }

        $section->delete();

        return back()->with('success', 'Section deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Normalise break time fields before validation:
     * - Empty strings → null (HTML time inputs submit "" when blank)
     * - "HH:MM:SS" → "HH:MM" (MySQL TIME columns include seconds on round-trip)
     */
    private function normaliseBreakTimes(Request $request, array $fields = ['recess_start', 'recess_end', 'lunch_start', 'lunch_end']): array
    {
        $patch  = [];
        foreach ($fields as $field) {
            $val = $request->input($field);
            if ($val === '' || $val === null) {
                $patch[$field] = null;
            } else {
                // Strip trailing seconds component if present (e.g. "09:00:00" → "09:00")
                $patch[$field] = preg_replace('/^(\d{1,2}:\d{2}):\d{2}$/', '$1', (string) $val);
            }
        }

        return $patch;
    }

    private function assertSectionBlockAvailable(
        Request $request,
        Section $section,
        string $day,
        string $type,
        ?array $window,
        ScheduleBlockAvailabilityService $availability,
    ): int {
        $request->validate(['academic_term_id' => 'nullable|integer|exists:academic_terms,id']);
        $termId = (int) ($request->input('academic_term_id') ?: AcademicTerm::where('is_current', true)->value('id'));
        if (! $termId) {
            abort(422, 'No academic term is available.');
        }

        $term = AcademicTerm::findOrFail($termId);
        $this->scopeLocks->assertScopeUnlocked($term, 'section', null, (int) $section->id);

        $sections = $availability->affectedSections($termId, 'section', $type, $day, null, (int) $section->id);
        $availability->assertAvailable($sections, $termId, $day, $window);

        return $termId;
    }

    private function mapSection(Section $s): array
    {
        return [
            'id'           => $s->id,
            'sectionname'  => $s->sectionname,
            'levelid'      => $s->levelid,
            'full_label'   => $s->full_label,
            'section_code' => $s->section_code,
            'capacity'     => $s->capacity,
            'classroom_id' => $s->classroom_id,
            'classroom'    => $s->classroom ? ['id' => $s->classroom->id, 'name' => $s->classroom->name, 'code' => $s->classroom->code] : null,
            'recess_start' => $s->recess_start,
            'recess_end'   => $s->recess_end,
            'lunch_start'  => $s->lunch_start,
            'lunch_end'    => $s->lunch_end,
            'is_active'    => $s->is_active,
            'adviser'      => $s->adviserUser ? ['id' => $s->adviserUser->id, 'name' => $s->adviserUser->name] : null,
            'school_year'  => $s->flSchoolYear ? ['id' => $s->flSchoolYear->id, 'name' => $s->flSchoolYear->name] : null,
        ];
    }
}
