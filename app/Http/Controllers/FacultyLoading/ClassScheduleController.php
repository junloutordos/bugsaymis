<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleApprovalBatch;
use App\Models\FacultyLoading\ClassScheduleSwapRequest;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\FacultyLoading\TeacherOfficialTime;
use App\Models\Office;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
use App\Services\FacultyLoading\LoadComputationService;
use App\Services\FacultyLoading\ScheduleValidationService;
use App\Services\FacultyLoading\SchedulingConstants;
use App\Services\PersonNameFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClassScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleValidationService $validation,
        private readonly LoadComputationService $loads,
        private readonly DigitalSignatureService $signatures,
        private readonly PersonNameFormatter $names,
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

        if ($user->hasPermission('faculty_loading.approve') || $user->hasRole('OCD')) {
            return ['level' => 'review', 'faculty_ids' => null];
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

        if ($cap['faculty_ids'] === null) {
            return false;
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

    public function printSection(Request $request, Section $section): Response
    {
        $this->authorize('faculty_loading.manage');

        $term = $this->resolvePrintTerm($request, $section->school_year_id);
        $section->loadMissing(['flSchoolYear', 'adviserUser:id,name', 'classroom:id,name,code']);

        return $this->renderPrintSchedule(
            'section',
            [
                'id' => $section->id,
                'name' => $section->sectionname,
                'grade_level' => $section->levelid,
                'adviser' => $section->adviserUser?->name,
                'classroom' => $section->classroom?->code ?? $section->classroom?->name,
            ],
            $term,
            ClassSchedule::where('section_id', $section->id),
            (int) $section->levelid,
        );
    }

    public function printFaculty(Request $request, User $faculty): Response
    {
        $cap = $this->scheduleCapability();

        abort_unless(
            $cap['level'] === 'manage' || in_array((int) $faculty->id, $cap['faculty_ids'] ?? [], true),
            403,
        );

        $term = $this->resolvePrintTerm($request);

        return $this->renderPrintSchedule(
            'faculty',
            [
                'id' => $faculty->id,
                'name' => $this->names->formal($faculty),
                'position' => $faculty->position,
            ],
            $term,
            ClassSchedule::where('user_id', $faculty->id),
            null,
            [
                'loadSummary' => $this->facultyLoadSummary($faculty->id, $term->id),
                'officialTimes' => $this->facultyOfficialTimes($faculty->id),
            ],
        );
    }

    /** "Chemistry 1 (G8)(12 units), Research Advisory (1), ..." — mirrors the CID Excel Load line. */
    private function facultyLoadSummary(int $facultyId, int $termId): ?string
    {
        $load = FacultyLoad::with(['assignments.subject', 'assignments.designation'])
            ->where('user_id', $facultyId)
            ->where('academic_term_id', $termId)
            ->first();

        if (! $load) {
            return null;
        }

        $formatUnits = fn (float $units) => rtrim(rtrim(number_format($units, 2, '.', ''), '0'), '.');

        $parts = [];

        foreach ($load->assignments->where('assignment_type', 'teaching')->groupBy('subject_id') as $group) {
            $subject = $group->first()->subject;
            if (! $subject) {
                continue;
            }
            $grade = $subject->grade_level > 0 ? " (G{$subject->grade_level})" : '';
            $parts[] = $subject->name.$grade.' ('.$formatUnits($group->sum(fn ($a) => (float) $a->load_units)).' units)';
        }

        foreach ($load->assignments->where('assignment_type', '<>', 'teaching') as $assignment) {
            $label = $assignment->designation?->name
                ?? $assignment->description
                ?? ucfirst(str_replace('_', ' ', $assignment->assignment_type));
            $parts[] = $label.' ('.$formatUnits((float) $assignment->load_units).')';
        }

        return $parts ? implode(', ', $parts) : null;
    }

    /** { Monday: {start, end}, ... } — official working time per day, defaulting to the early shift. */
    private function facultyOfficialTimes(int $facultyId): array
    {
        $rows = TeacherOfficialTime::forTeacher($facultyId)->get()->keyBy('day_of_week');

        $times = [];
        foreach (SchedulingConstants::DAYS as $day) {
            $row = $rows->get($day);
            $times[$day] = [
                'start' => substr($row?->start_time ?? '07:30', 0, 5),
                'end' => substr($row?->end_time ?? '16:30', 0, 5),
            ];
        }

        return $times;
    }

    private function resolvePrintTerm(Request $request, ?int $schoolYearId = null): AcademicTerm
    {
        $termId = $request->integer('term_id') ?: AcademicTerm::where('is_current', true)->value('id');

        abort_unless($termId, 404, 'No academic term is available.');

        return AcademicTerm::with('schoolYear')
            ->when($schoolYearId, fn ($q) => $q->where('school_year_id', $schoolYearId))
            ->findOrFail($termId);
    }

    /** CID Chief + Campus Director for the print signatory block (same resolution as FacultyLoadController). */
    private function printSignatories(AcademicTerm $term, ?User $conforme = null): array
    {
        $batch = $this->printApprovalBatch($term);

        $signatories = $this->printSharedSignatories($batch);
        $signatories['conforme'] = $conforme
            ? $this->formatPrintSignatory($batch, $conforme, 'Faculty', 'conforme')
            : null;

        return $signatories;
    }

    private function printApprovalBatch(AcademicTerm $term): ?ClassScheduleApprovalBatch
    {
        return ClassScheduleApprovalBatch::with(['submitter.pds.personalInfo', 'approver.pds.personalInfo', 'signatures.signer.pds.personalInfo'])
            ->where('academic_term_id', $term->id)
            ->whereIn('status', ['pending_ocd', 'approved'])
            ->latest('id')->first();
    }

    /** batch_status + Prepared/Approved — identical for every sheet of a term. */
    private function printSharedSignatories(?ClassScheduleApprovalBatch $batch): array
    {
        $cidChief = User::whereHas('roles', fn ($q) => $q->where('name', 'CID Chief'))
            ->where('status', '<>', 'inactive')
            ->first(['id', 'name', 'position']);

        $director = User::where('position', 'like', '%Director%')
            ->where('position', 'not like', '%Assistant%')
            ->where('status', '<>', 'inactive')
            ->first(['id', 'name', 'position']);

        return [
            'batch_status' => $batch?->status,
            'prepared' => $this->formatPrintSignatory(
                $batch,
                $batch?->submitter ?? $cidChief,
                'Chief, Curriculum and Instruction Division',
                'prepared',
            ),
            'approved' => $this->formatPrintSignatory(
                $batch,
                $batch?->approver ?? $director,
                'Campus Director',
                'approved',
            ),
        ];
    }

    private function formatPrintSignatory(?ClassScheduleApprovalBatch $batch, ?User $user, string $position, string $stage): ?array
    {
        if (! $user) {
            return null;
        }

        $record = ($batch?->signatures ?? collect())->first(fn ($sig) => ($sig->metadata['stage'] ?? '') === $stage
            && ($stage !== 'conforme' || (int) $sig->signer_id === (int) $user->id)
        );

        return [
            'name' => $this->names->formal($user),
            'position' => $user->position ?? $position,
            'signature' => $record
                ? $this->signatures->getImageDataUriFromPath($record->metadata['signature_path'] ?? $record->signer?->electronic_signature)
                : null,
            'signed_at' => $record?->signed_at?->format('F j, Y'),
        ];
    }

    /** One sheet's worth of print props: owner + schedules + dayConfigs (+ extras). */
    private function buildPrintSheet(
        array $owner,
        AcademicTerm $term,
        Builder $query,
        ?int $gradeLevel = null,
        array $extra = [],
    ): array {
        $dayOrder = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')";

        $schedules = $query
            ->with(['subject', 'classroom', 'faculty:id,name', 'section:id,sectionname,levelid'])
            ->where('academic_term_id', $term->id)
            ->occupying()
            ->orderByRaw($dayOrder)
            ->orderBy('start_time')
            ->get()
            ->map(fn ($schedule) => $schedule->toCalendarArray())
            ->values();

        $dayConfigs = [];
        if ($gradeLevel !== null) {
            foreach (SchedulingConstants::DAYS as $day) {
                $window = SchedulingConstants::getEffectiveClassWindow($gradeLevel, $day);
                $dayConfigs[$day] = [
                    'start' => $window['start'] ?? null,
                    'end' => $window['end'] ?? null,
                    'blocked' => SchedulingConstants::getDisplayBlockedSlots($gradeLevel, $day),
                ];
            }
        }

        return [
            'owner' => $owner,
            'schedules' => $schedules,
            'dayConfigs' => $dayConfigs,
            ...$extra,
        ];
    }

    private function renderPrintSchedule(
        string $scheduleType,
        array $owner,
        AcademicTerm $term,
        Builder $query,
        ?int $gradeLevel = null,
        array $extra = [],
    ): Response {
        return Inertia::render('FacultyLoading/Schedules/Print', [
            'scheduleType' => $scheduleType,
            'term' => [
                'id' => $term->id,
                'label' => $term->full_label,
                'school_year' => $term->schoolYear?->name,
            ],
            'signatories' => $this->printSignatories(
                $term,
                $scheduleType === 'faculty' ? User::find($owner['id']) : null,
            ),
            ...$this->buildPrintSheet($owner, $term, $query, $gradeLevel, $extra),
        ]);
    }

    /**
     * Batch print — one document with one official sheet per section (or per
     * faculty) that has occupying schedules in the term. Prepared/Approved
     * signatories are identical on every sheet, so they're sent once and
     * merged client-side; faculty sheets carry only their own Conforme.
     */
    public function printBatchSchedules(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $type = (string) $request->query('type');
        abort_unless(in_array($type, ['section', 'faculty'], true), 404);

        $term = $this->resolvePrintTerm($request);
        $batch = $this->printApprovalBatch($term);

        $sheets = [];

        if ($type === 'section') {
            $sectionIds = ClassSchedule::where('academic_term_id', $term->id)
                ->occupying()
                ->whereNotNull('section_id')
                ->distinct()
                ->pluck('section_id');

            $sections = Section::whereIn('id', $sectionIds)
                ->when($request->integer('section_id'), fn ($q, $id) => $q->where('id', $id))
                ->orderBy('levelid')
                ->orderBy('sectionname')
                ->get();

            foreach ($sections as $section) {
                $sheets[] = $this->buildPrintSheet(
                    [
                        'id' => $section->id,
                        'name' => $section->sectionname,
                        'grade_level' => $section->levelid,
                    ],
                    $term,
                    ClassSchedule::where('section_id', $section->id),
                    (int) $section->levelid,
                );
            }
        } else {
            $facultyIds = ClassSchedule::where('academic_term_id', $term->id)
                ->occupying()
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            $faculty = User::whereIn('id', $facultyIds)
                ->where('status', '<>', 'inactive')
                ->where('name', 'not like', 'TBA%')
                ->when($request->integer('faculty_id'), fn ($q, $id) => $q->where('id', $id))
                ->orderBy('name')
                ->get(['id', 'name', 'position']);

            foreach ($faculty as $member) {
                $sheets[] = $this->buildPrintSheet(
                    [
                        'id' => $member->id,
                        'name' => $this->names->formal($member),
                        'position' => $member->position,
                    ],
                    $term,
                    ClassSchedule::where('user_id', $member->id),
                    null,
                    [
                        'loadSummary' => $this->facultyLoadSummary($member->id, $term->id),
                        'officialTimes' => $this->facultyOfficialTimes($member->id),
                        'conforme' => $this->formatPrintSignatory($batch, $member, 'Faculty', 'conforme'),
                    ],
                );
            }
        }

        return Inertia::render('FacultyLoading/Schedules/PrintBatch', [
            'scheduleType' => $type,
            'term' => [
                'id' => $term->id,
                'label' => $term->full_label,
                'school_year' => $term->schoolYear?->name,
            ],
            'sheets' => $sheets,
            'sharedSignatories' => $this->printSharedSignatories($batch),
        ]);
    }

    private function renderCalendar(Request $request, array $cap, string $pageMode): Response
    {
        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId = $request->input('term_id', $currentTerm?->id);
        $sectionId = $request->input('section_id');
        $facultyId = $request->input('faculty_id');

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
                'id' => $t->id,
                'label' => $t->full_label,
                'is_current' => $t->is_current,
                'school_year_id' => $t->school_year_id,
            ]);

        // Division/Office are the live Data Management assignment (kept current
        // by UserController whenever an admin edits a faculty member's unit) —
        // used to group the "By Faculty" calendar view by org unit.
        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->where(fn ($q) => $q->where('on_study_leave', false)->orWhereNull('on_study_leave'))
            ->when($cap['faculty_ids'] !== null, fn ($q) => $q->whereIn('id', $cap['faculty_ids']))
            ->with(['division:id,division_name', 'office:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'division_id', 'office_id'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'position' => $u->position,
                'division_name' => $u->division?->division_name,
                'office_name' => $u->office?->name,
            ]);

        $subjects = Subject::active()->orderBy('code')->get(['id', 'code', 'name', 'subject_type', 'load_units']);
        $classrooms = Classroom::available()->orderBy('name')->get(['id', 'name', 'code', 'classroom_type', 'capacity']);

        // Sections filtered to the term's school year (fall back to all active sections)
        $syId = $currentTerm?->school_year_id;
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
                    'start' => $window['start'] ?? null,
                    'end' => $window['end'] ?? null,
                    'blocked' => SchedulingConstants::getDisplayBlockedSlots($grade, $day),
                ];
            }
        }

        // The unplaced-subjects tray is a teaching-placement tool — manage only.
        $unplacedLoads = $cap['level'] === 'manage'
            ? $this->buildUnplacedLoads($termId, $sectionId, $facultyId, $lockedFacultyIds, $sections)
            : [];

        $approvalBatch = null;
        if ($termId) {
            $approvalQuery = ClassScheduleApprovalBatch::with(['submitter:id,name', 'approver:id,name'])
                ->where('academic_term_id', $termId);
            $approvalBatch = (clone $approvalQuery)->whereIn('status', ['pending_ocd', 'approved'])->latest('id')->first()
                ?? $approvalQuery->latest('id')->first();
        }
        $conformeSigned = $approvalBatch?->status === 'approved'
            && $approvalBatch->signatures()->where('signer_id', Auth::id())
                ->where('metadata->stage', 'conforme')->exists();

        $swapRequests = $termId ? ClassScheduleSwapRequest::with([
            'requester:id,name',
            'sourceSchedule.subject:id,code,name',
            'sourceSchedule.section:id,sectionname,levelid',
            'sourceSchedule.faculty:id,name',
        ])
            ->where('academic_term_id', $termId)
            ->when($cap['level'] !== 'manage' || $pageMode === 'my', fn ($q) => $q->where('requested_by', Auth::id()))
            ->latest('id')->limit(50)->get()
            ->map(fn ($swap) => [
                'id' => $swap->id,
                'status' => $swap->status,
                'reason' => $swap->reason,
                'preferences' => $swap->preferences,
                'requester' => $swap->requester?->name,
                'source' => $swap->sourceSchedule ? [
                    'id' => $swap->sourceSchedule->id,
                    'subject' => $swap->sourceSchedule->subject?->code ?? $swap->sourceSchedule->subject?->name,
                    'faculty' => $swap->sourceSchedule->faculty?->name,
                    'section' => $swap->sourceSchedule->section?->sectionname,
                    'grade' => $swap->sourceSchedule->section?->levelid,
                    'day' => $swap->sourceSchedule->day_of_week,
                    'start' => substr($swap->sourceSchedule->start_time, 0, 5),
                    'end' => substr($swap->sourceSchedule->end_time, 0, 5),
                ] : null,
                'approval_batch_id' => $swap->approval_batch_id,
                'created_at' => $swap->created_at?->toIso8601String(),
            ])->values() : collect();

        return Inertia::render('FacultyLoading/Schedules/Index', [
            'schedules' => $schedules,
            'terms' => $terms,
            'faculty' => $faculty,
            'subjects' => $subjects,
            'classrooms' => $classrooms,
            'sections' => $sections,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters' => $request->only(['term_id', 'section_id', 'faculty_id']),
            'dayConfigsByGrade' => $dayConfigsByGrade,
            'unplacedLoads' => $unplacedLoads,
            'capability' => ['level' => $cap['level']],
            'pageMode' => $pageMode,
            'approvalBatch' => $approvalBatch ? [
                'id' => $approvalBatch->id,
                'status' => $approvalBatch->status,
                'approval_type' => $approvalBatch->approval_type,
                'submitted_by' => $approvalBatch->submitter?->name,
                'submitted_at' => $approvalBatch->submitted_at?->toIso8601String(),
                'approved_by' => $approvalBatch->approver?->name,
                'approved_at' => $approvalBatch->approved_at?->toIso8601String(),
                'return_remarks' => $approvalBatch->return_remarks,
                'locked' => in_array($approvalBatch->status, ['pending_ocd', 'approved'], true),
                'conforme_signed' => $conformeSigned,
            ] : null,
            'canSubmitSchedule' => Auth::user()->hasRole('CID Chief') || Auth::user()->isSuperAdmin(),
            'swapRequests' => $swapRequests,
            'canRequestSwap' => $cap['level'] !== 'review',
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
                $required = max(1, (int) round((float) ($la->subject->load_units ?? 1)));
                $scheduled = (int) ($scheduledCounts[$la->id] ?? 0);
                $stillNeeded = max(0, $required - $scheduled);

                if ($stillNeeded === 0) {
                    return null;
                }

                $section = $sectionsById->get($la->section_id);

                return [
                    'load_assignment_id' => $la->id,
                    'subject' => $la->subject ? [
                        'id' => $la->subject->id,
                        'code' => $la->subject->code,
                        'name' => $la->subject->name,
                        'is_elective' => $la->subject->grade_level === 0 || $la->subject->subject_type === 'elective',
                    ] : null,
                    'faculty' => $la->faculty ? [
                        'id' => $la->faculty->id,
                        'name' => $la->faculty->name,
                    ] : null,
                    'section_id' => $la->section_id,
                    'section_name' => $section?->sectionname ?? "Section {$la->section_id}",
                    'grade_level' => $section?->levelid,
                    'still_needed' => $stillNeeded,
                    'is_locked' => in_array((int) $la->user_id, $lockedFacultyIds, true),
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
            'entry_type' => 'nullable|in:class,non_teaching',
            'faculty_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'section_id' => 'nullable|integer',
            'classroom_id' => 'nullable|integer',
            'academic_term_id' => 'required|integer',
            'day_of_week' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'exclude_id' => 'nullable|integer',
        ]);
        if (ClassScheduleApprovalService::termIsLocked((int) $data['academic_term_id'])) {
            return response()->json(['message' => 'This term schedule is locked for OCD approval.'], 423);
        }

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
            'faculty_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'section_id' => 'required|integer',
            'classroom_id' => 'required|exists:classrooms,id',
            'school_year_id' => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'in:active,tentative',
            'remarks' => 'nullable|string|max:500',
            'force' => 'boolean',   // override warnings only
            'load_assignment_id' => 'nullable|exists:load_assignments,id',
        ]);
        if (ClassScheduleApprovalService::termIsLocked((int) $data['academic_term_id'])) {
            return back()->withErrors(['schedule' => 'This term schedule is locked for OCD approval.']);
        }

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
            'user_id' => $data['faculty_id'],
            'subject_id' => $data['subject_id'],
            'section_id' => $data['section_id'],
            'classroom_id' => $data['classroom_id'],
            'school_year_id' => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => $data['status'] ?? 'active',
            'remarks' => $data['remarks'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $msg = 'Schedule created.';
        if (! empty($validation['warnings'])) {
            $msg .= ' Note: '.implode(' ', $validation['warnings']);
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
            'title' => 'required|string|max:120',
            'category' => 'nullable|string|max:30',
            'faculty_id' => 'nullable|exists:users,id',
            'section_id' => 'nullable|integer',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'school_year_id' => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'in:active,tentative',
            'remarks' => 'nullable|string|max:500',
        ]);
        if (ClassScheduleApprovalService::termIsLocked((int) $data['academic_term_id'])) {
            return back()->withErrors(['schedule' => 'This term schedule is locked for OCD approval.']);
        }

        $cap = $this->scheduleCapability();
        if (! $this->canTouchNonTeaching($cap, $data['faculty_id'] ?? null)) {
            return back()->withErrors(['faculty_id' => 'You can only add non-teaching blocks to your own schedule'
                .($cap['level'] === 'unit' ? " or your unit's faculty." : '.')]);
        }

        $validation = $this->validation->validateNonTeaching($data);
        if (! empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->with('validation_result', $validation);
        }

        ClassSchedule::create([
            'entry_type' => 'non_teaching',
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'user_id' => $data['faculty_id'] ?? null,
            'subject_id' => null,
            'section_id' => $data['section_id'] ?? null,
            'classroom_id' => $data['classroom_id'] ?? null,
            'school_year_id' => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => $data['status'] ?? 'active',
            'remarks' => $data['remarks'] ?? null,
            'created_by' => Auth::id(),
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
            'faculty_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'section_id' => 'required|integer',
            'classroom_id' => 'required|exists:classrooms,id',
            'school_year_id' => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'in:active,tentative,cancelled',
            'remarks' => 'nullable|string|max:500',
            'force' => 'boolean',
        ]);
        if (ClassScheduleApprovalService::termIsLocked((int) $classSchedule->academic_term_id)
            || ClassScheduleApprovalService::termIsLocked((int) $data['academic_term_id'])) {
            return back()->withErrors(['schedule' => 'This term schedule is locked for OCD approval.']);
        }

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
            'user_id' => $data['faculty_id'],
            'subject_id' => $data['subject_id'],
            'section_id' => $data['section_id'],
            'classroom_id' => $data['classroom_id'],
            'school_year_id' => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => $data['status'] ?? $classSchedule->status,
            'remarks' => $data['remarks'] ?? null,
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
            'title' => 'required|string|max:120',
            'category' => 'nullable|string|max:30',
            'faculty_id' => 'nullable|exists:users,id',
            'section_id' => 'nullable|integer',
            'classroom_id' => 'nullable|exists:classrooms,id',
            'school_year_id' => 'required|exists:school_years,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'status' => 'in:active,tentative,cancelled',
            'remarks' => 'nullable|string|max:500',
        ]);
        if (ClassScheduleApprovalService::termIsLocked((int) $classSchedule->academic_term_id)
            || ClassScheduleApprovalService::termIsLocked((int) $data['academic_term_id'])) {
            return back()->withErrors(['schedule' => 'This term schedule is locked for OCD approval.']);
        }

        // The reassigned target must also be within reach.
        if (! $this->canTouchNonTeaching($cap, $data['faculty_id'] ?? null)) {
            return back()->withErrors(['faculty_id' => 'You cannot move this block to that faculty member.']);
        }

        $validation = $this->validation->validateNonTeaching($data, $classSchedule->id);
        if (! empty($validation['errors'])) {
            return back()->withErrors($validation['errors'])->with('validation_result', $validation);
        }

        $classSchedule->update([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'user_id' => $data['faculty_id'] ?? null,
            'section_id' => $data['section_id'] ?? null,
            'classroom_id' => $data['classroom_id'] ?? null,
            'school_year_id' => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => $data['status'] ?? $classSchedule->status,
            'remarks' => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Non-teaching block updated.');
    }

    public function destroy(ClassSchedule $classSchedule): RedirectResponse
    {
        if (ClassScheduleApprovalService::termIsLocked((int) $classSchedule->academic_term_id)) {
            return back()->withErrors(['schedule' => 'This term schedule is locked for OCD approval.']);
        }
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
