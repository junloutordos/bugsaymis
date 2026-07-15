<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Office;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use App\Services\FacultyLoading\ScheduleValidationService;
use App\Services\FacultyLoading\SchedulingConstants;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClassScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleValidationService $validation,
        private readonly LoadComputationService    $loads,
    ) {}

    /**
     * Resolve what the current user may do on the schedule calendar.
     *
     *   manage — faculty_loading.manage (CID Chief / admin): full teaching +
     *            non-teaching CRUD for everyone.
     *   unit   — Academic Unit Head (heads an Office under the CID division):
     *            non-teaching blocks for their unit's faculty only.
     *   self   — any other faculty_loading.view_own holder: own non-teaching
     *            blocks only.
     *
     * @return array{level: string, faculty_ids: array<int>|null}
     */
    private function scheduleCapability(): array
    {
        $user = Auth::user();

        if ($user->isSuperAdmin() || $user->hasPermission('faculty_loading.manage')) {
            return ['level' => 'manage', 'faculty_ids' => null];
        }

        $cidDivisionId = Division::where('acronym', 'CID')->value('id');
        $unitIds = Office::where('unit_head', $user->id)
            ->when($cidDivisionId, fn ($q) => $q->where('division_id', $cidDivisionId))
            ->pluck('id');

        if ($unitIds->isNotEmpty()) {
            $facultyIds = User::whereIn('office_id', $unitIds)
                ->pluck('id')
                ->push($user->id)
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            return ['level' => 'unit', 'faculty_ids' => $facultyIds];
        }

        return ['level' => 'self', 'faculty_ids' => [(int) $user->id]];
    }

    /** True if the capability allows creating/editing a non-teaching block for this faculty. */
    private function canTouchNonTeaching(array $cap, ?int $facultyId): bool
    {
        if ($cap['level'] === 'manage') {
            return true;
        }

        // Section-only blocks (no faculty) are a manage-level action.
        return $facultyId !== null && in_array($facultyId, $cap['faculty_ids'], true);
    }

    public function index(Request $request): Response
    {
        return $this->renderCalendar($request, $this->scheduleCapability(), 'admin');
    }

    /**
     * My Faculty Schedule — the same calendar pinned to the signed-in user's
     * own schedule for EVERYONE, including managers. Capability is forced to
     * self, so toCalendarArray() marks CID-plotted teaching rows can_edit=false
     * and the page is personal-view-only by construction. Mutations still go
     * through store/update/destroy, which re-resolve the real capability.
     */
    public function mySchedule(Request $request): Response
    {
        return $this->renderCalendar(
            $request,
            ['level' => 'self', 'faculty_ids' => [(int) Auth::id()]],
            'my',
        );
    }

    private function renderCalendar(Request $request, array $cap, string $pageMode): Response
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);
        $sectionId   = $request->input('section_id');
        $facultyId   = $request->input('faculty_id');

        // Self mode is pinned to the user's own calendar.
        if ($cap['level'] === 'self') {
            $facultyId = Auth::id();
            $sectionId = null;
        }

        // Faculty whose load is locked for this term — schedules/unplaced loads for
        // them must render as non-draggable on the calendar.
        $lockedFacultyIds = FacultyLoad::where('academic_term_id', $termId)
            ->where('is_locked', true)
            ->pluck('user_id')
            ->all();

        $query = ClassSchedule::with(['subject', 'classroom', 'faculty:id,name', 'section:id,sectionname,levelid'])
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($facultyId, fn ($q) => $q->where('class_schedules.user_id', $facultyId))
            // Unit heads only see their own unit's faculty calendars.
            ->when($cap['level'] === 'unit', fn ($q) => $q->whereIn('class_schedules.user_id', $cap['faculty_ids']));

        // Order by grade level + section name + day + time for clean grouping
        $dayOrder = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')";
        // leftJoin, not join — non-teaching blocks may have no section and must
        // still appear on the calendar.
        $schedules = $query
            ->leftJoin('sections as sec', 'sec.id', '=', 'class_schedules.section_id')
            ->orderBy('sec.levelid')
            ->orderByRaw('sec.sectionname')
            ->orderByRaw($dayOrder)
            ->orderBy('class_schedules.start_time')
            ->select('class_schedules.*')
            ->get()
            ->map(fn ($s) => $s->toCalendarArray($lockedFacultyIds, $cap));

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'label'          => $t->full_label,
                'is_current'     => $t->is_current,
                'school_year_id' => $t->school_year_id,
            ]);

        // Division/Office are the live Data Management assignment (kept current
        // by UserController whenever an admin edits a faculty member's unit) —
        // used to group the "By Faculty" calendar view by org unit.
        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->where(fn ($q) => $q->where('on_study_leave', false)->orWhereNull('on_study_leave'))
            ->when($cap['level'] !== 'manage', fn ($q) => $q->whereIn('id', $cap['faculty_ids']))
            ->with(['division:id,division_name', 'office:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'division_id', 'office_id'])
            ->map(fn ($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'position'      => $u->position,
                'division_name' => $u->division?->division_name,
                'office_name'   => $u->office?->name,
            ]);

        $subjects   = Subject::active()->orderBy('code')->get(['id', 'code', 'name', 'subject_type', 'load_units']);
        $classrooms = Classroom::available()->orderBy('name')->get(['id', 'name', 'code', 'classroom_type', 'capacity']);

        // Sections filtered to the term's school year (fall back to all active sections)
        $syId     = $currentTerm?->school_year_id;
        $sections = Section::when($syId, fn ($q) => $q->where('school_year_id', $syId))
            ->where('is_active', true)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(['id', 'sectionname', 'levelid']);

        // Per-grade, per-day school config for calendar rendering (blocked
        // periods, class hours) — derived directly from SchedulingConstants
        // so it can never drift out of sync with what the generator actually
        // treats as blocked (recess/lunch/homeroom/wellness/ALP all vary by
        // grade, so a single flat schedule can't represent this accurately).
        $dayConfigsByGrade = [];
        foreach (array_keys(SchedulingConstants::GRADE_SECTIONS) as $grade) {
            foreach (SchedulingConstants::DAYS as $day) {
                $window = SchedulingConstants::getEffectiveClassWindow($grade, $day);
                $dayConfigsByGrade[$grade][$day] = [
                    'start'   => $window['start'] ?? null,
                    'end'     => $window['end'] ?? null,
                    'blocked' => SchedulingConstants::getBlockedSlots($grade, $day),
                ];
            }
        }

        // The unplaced-subjects tray is a teaching-placement tool — manage only.
        $unplacedLoads = $cap['level'] === 'manage'
            ? $this->buildUnplacedLoads($termId, $sectionId, $facultyId, $lockedFacultyIds, $sections)
            : [];

        return Inertia::render('FacultyLoading/Schedules/Index', [
            'schedules'     => $schedules,
            'terms'         => $terms,
            'faculty'       => $faculty,
            'subjects'      => $subjects,
            'classrooms'    => $classrooms,
            'sections'      => $sections,
            'currentTerm'   => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'       => $request->only(['term_id', 'section_id', 'faculty_id']),
            'dayConfigsByGrade' => $dayConfigsByGrade,
            'unplacedLoads' => $unplacedLoads,
            'capability'    => ['level' => $cap['level']],
            'pageMode'      => $pageMode,
        ]);
    }

    /**
     * Teaching load assignments for the filtered term/section/faculty that still
     * need one or more weekly sessions placed on the calendar — drag targets for
     * the "unplaced subjects" tray.
     */
    private function buildUnplacedLoads(
        ?int $termId,
        ?int $sectionId,
        ?int $facultyId,
        array $lockedFacultyIds,
        Collection $sections
    ): array {
        if (! $termId) {
            return [];
        }

        $loads = LoadAssignment::with(['subject:id,code,name,load_units,grade_level,subject_type', 'faculty:id,name'])
            ->where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('section_id')
            ->whereNotNull('subject_id')
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId))
            ->get();

        if ($loads->isEmpty()) {
            return [];
        }

        $scheduledCounts = ClassSchedule::occupying()
            ->whereIn('load_assignment_id', $loads->pluck('id'))
            ->selectRaw('load_assignment_id, COUNT(*) as cnt')
            ->groupBy('load_assignment_id')
            ->pluck('cnt', 'load_assignment_id');

        $sectionsById = $sections->keyBy('id');

        return $loads
            ->map(function ($la) use ($scheduledCounts, $sectionsById, $lockedFacultyIds) {
                $required    = max(1, (int) round((float) ($la->subject->load_units ?? 1)));
                $scheduled   = (int) ($scheduledCounts[$la->id] ?? 0);
                $stillNeeded = max(0, $required - $scheduled);

                if ($stillNeeded === 0) {
                    return null;
                }

                $section = $sectionsById->get($la->section_id);

                return [
                    'load_assignment_id' => $la->id,
                    'subject'            => $la->subject ? [
                        'id'          => $la->subject->id,
                        'code'        => $la->subject->code,
                        'name'        => $la->subject->name,
                        'is_elective' => $la->subject->grade_level === 0 || $la->subject->subject_type === 'elective',
                    ] : null,
                    'faculty' => $la->faculty ? [
                        'id'   => $la->faculty->id,
                        'name' => $la->faculty->name,
                    ] : null,
                    'section_id'   => $la->section_id,
                    'section_name' => $section?->sectionname ?? "Section {$la->section_id}",
                    'grade_level'  => $section?->levelid,
                    'still_needed' => $stillNeeded,
                    'is_locked'    => in_array((int) $la->user_id, $lockedFacultyIds, true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Validate a proposed schedule without saving (used by the form).
     */
    public function validateSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_type'       => 'nullable|in:class,non_teaching',
            'faculty_id'       => 'nullable|integer',
            'subject_id'       => 'nullable|integer',
            'section_id'       => 'nullable|integer',
            'classroom_id'     => 'nullable|integer',
            'academic_term_id' => 'required|integer',
            'day_of_week'      => 'required|string',
            'start_time'       => 'required|string',
            'end_time'         => 'required|string',
            'exclude_id'       => 'nullable|integer',
        ]);

        $result = ($data['entry_type'] ?? 'class') === 'non_teaching'
            ? $this->validation->validateNonTeaching($data, $data['exclude_id'] ?? null)
            : $this->validation->validate($data, $data['exclude_id'] ?? null);

        return response()->json($result);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('entry_type') === 'non_teaching') {
            return $this->storeNonTeaching($request);
        }

        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'faculty_id'         => 'required|exists:users,id',
            'subject_id'         => 'required|exists:subjects,id',
            'section_id'         => 'required|integer',
            'classroom_id'       => 'required|exists:classrooms,id',
            'school_year_id'     => 'required|exists:school_years,id',
            'academic_term_id'   => 'required|exists:academic_terms,id',
            'day_of_week'        => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time'         => 'required|date_format:H:i',
            'end_time'           => 'required|date_format:H:i|after:start_time',
            'status'             => 'in:active,tentative',
            'remarks'            => 'nullable|string|max:500',
            'force'              => 'boolean',   // override warnings only
            'load_assignment_id' => 'nullable|exists:load_assignments,id',
        ]);

        $validation = $this->validation->validate($data);

        // Hard block: errors must be resolved. Warnings never block — `force`
        // (the "I acknowledge the warnings" checkbox) only matters for those,
        // and is implicitly honored since warnings alone never reach this branch.
        if (! empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->with('validation_result', $validation);
        }

        $facultyLoad = FacultyLoad::where('user_id', $data['faculty_id'])
            ->where('academic_term_id', $data['academic_term_id'])
            ->first();
        if ($facultyLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        $schedule = ClassSchedule::create([
            'load_assignment_id' => $data['load_assignment_id'] ?? null,
            'user_id'            => $data['faculty_id'],
            'subject_id'         => $data['subject_id'],
            'section_id'         => $data['section_id'],
            'classroom_id'       => $data['classroom_id'],
            'school_year_id'     => $data['school_year_id'],
            'academic_term_id'   => $data['academic_term_id'],
            'day_of_week'        => $data['day_of_week'],
            'start_time'         => $data['start_time'],
            'end_time'           => $data['end_time'],
            'status'             => $data['status'] ?? 'active',
            'remarks'            => $data['remarks'] ?? null,
            'created_by'         => Auth::id(),
        ]);

        $msg = 'Schedule created.';
        if (! empty($validation['warnings'])) {
            $msg .= ' Note: ' . implode(' ', $validation['warnings']);
        }

        return back()->with('success', $msg);
    }

    /**
     * Create a non-teaching block (consultation, research, advising, …).
     * No load linkage, no subject; the faculty-load lock does not apply
     * because blocks never change load units.
     */
    private function storeNonTeaching(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:120',
            'category'         => 'nullable|string|max:30',
            'faculty_id'       => 'nullable|exists:users,id',
            'section_id'       => 'nullable|integer',
            'classroom_id'     => 'nullable|exists:classrooms,id',
            'school_year_id'   => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'day_of_week'      => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'status'           => 'in:active,tentative',
            'remarks'          => 'nullable|string|max:500',
        ]);

        $cap = $this->scheduleCapability();
        if (! $this->canTouchNonTeaching($cap, $data['faculty_id'] ?? null)) {
            return back()->withErrors(['faculty_id' => 'You can only add non-teaching blocks to your own schedule'
                . ($cap['level'] === 'unit' ? " or your unit's faculty." : '.')]);
        }

        $validation = $this->validation->validateNonTeaching($data);
        if (! empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->with('validation_result', $validation);
        }

        ClassSchedule::create([
            'entry_type'       => 'non_teaching',
            'title'            => $data['title'],
            'category'         => $data['category'] ?? null,
            'user_id'          => $data['faculty_id'] ?? null,
            'subject_id'       => null,
            'section_id'       => $data['section_id'] ?? null,
            'classroom_id'     => $data['classroom_id'] ?? null,
            'school_year_id'   => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week'      => $data['day_of_week'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'status'           => $data['status'] ?? 'active',
            'remarks'          => $data['remarks'] ?? null,
            'created_by'       => Auth::id(),
        ]);

        return back()->with('success', 'Non-teaching block added.');
    }

    public function update(Request $request, ClassSchedule $classSchedule): RedirectResponse
    {
        if ($classSchedule->entry_type === 'non_teaching') {
            return $this->updateNonTeaching($request, $classSchedule);
        }

        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'faculty_id'         => 'required|exists:users,id',
            'subject_id'         => 'required|exists:subjects,id',
            'section_id'         => 'required|integer',
            'classroom_id'       => 'required|exists:classrooms,id',
            'school_year_id'     => 'required|exists:school_years,id',
            'academic_term_id'   => 'required|exists:academic_terms,id',
            'day_of_week'        => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time'         => 'required|date_format:H:i',
            'end_time'           => 'required|date_format:H:i|after:start_time',
            'status'             => 'in:active,tentative,cancelled',
            'remarks'            => 'nullable|string|max:500',
            'force'              => 'boolean',
        ]);

        $facultyLoad = FacultyLoad::where('user_id', $classSchedule->user_id)
            ->where('academic_term_id', $classSchedule->academic_term_id)
            ->first();
        if ($facultyLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        $validation = $this->validation->validate($data, $classSchedule->id);

        if (! empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->with('validation_result', $validation);
        }

        $classSchedule->update([
            'user_id'          => $data['faculty_id'],
            'subject_id'       => $data['subject_id'],
            'section_id'       => $data['section_id'],
            'classroom_id'     => $data['classroom_id'],
            'school_year_id'   => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week'      => $data['day_of_week'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'status'           => $data['status'] ?? $classSchedule->status,
            'remarks'          => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Schedule updated.');
    }

    /** Update a non-teaching block — capability-checked, lock-exempt. */
    private function updateNonTeaching(Request $request, ClassSchedule $classSchedule): RedirectResponse
    {
        $cap = $this->scheduleCapability();
        if (! $this->canTouchNonTeaching($cap, $classSchedule->user_id ? (int) $classSchedule->user_id : null)) {
            return back()->withErrors(['faculty_id' => 'You are not allowed to modify this non-teaching block.']);
        }

        $data = $request->validate([
            'title'            => 'required|string|max:120',
            'category'         => 'nullable|string|max:30',
            'faculty_id'       => 'nullable|exists:users,id',
            'section_id'       => 'nullable|integer',
            'classroom_id'     => 'nullable|exists:classrooms,id',
            'school_year_id'   => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'day_of_week'      => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'status'           => 'in:active,tentative,cancelled',
            'remarks'          => 'nullable|string|max:500',
        ]);

        // The reassigned target must also be within reach.
        if (! $this->canTouchNonTeaching($cap, $data['faculty_id'] ?? null)) {
            return back()->withErrors(['faculty_id' => 'You cannot move this block to that faculty member.']);
        }

        $validation = $this->validation->validateNonTeaching($data, $classSchedule->id);
        if (! empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->with('validation_result', $validation);
        }

        $classSchedule->update([
            'title'            => $data['title'],
            'category'         => $data['category'] ?? null,
            'user_id'          => $data['faculty_id'] ?? null,
            'section_id'       => $data['section_id'] ?? null,
            'classroom_id'     => $data['classroom_id'] ?? null,
            'school_year_id'   => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week'      => $data['day_of_week'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'status'           => $data['status'] ?? $classSchedule->status,
            'remarks'          => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Non-teaching block updated.');
    }

    public function destroy(ClassSchedule $classSchedule): RedirectResponse
    {
        // Non-teaching blocks are hard-deleted (no load/audit linkage to keep).
        if ($classSchedule->entry_type === 'non_teaching') {
            $cap = $this->scheduleCapability();
            if (! $this->canTouchNonTeaching($cap, $classSchedule->user_id ? (int) $classSchedule->user_id : null)) {
                return back()->withErrors(['faculty_id' => 'You are not allowed to remove this non-teaching block.']);
            }

            $classSchedule->delete();

            return back()->with('success', 'Non-teaching block removed.');
        }

        $this->authorize('faculty_loading.manage');

        $facultyLoad = FacultyLoad::where('user_id', $classSchedule->user_id)
            ->where('academic_term_id', $classSchedule->academic_term_id)
            ->first();
        if ($facultyLoad?->is_locked) {
            return back()->withErrors(['faculty_load_id' => 'This faculty load record is locked and cannot be modified.']);
        }

        $classSchedule->update(['status' => 'cancelled']);

        return back()->with('success', 'Schedule cancelled.');
    }

    // Presentation mapping moved to ClassSchedule::toCalendarArray() — shared
    // with any other page that renders a schedule calendar (e.g. Section Show).
}
