<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolDayConfig;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use App\Services\FacultyLoading\ScheduleValidationService;
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

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);
        $sectionId   = $request->input('section_id');
        $facultyId   = $request->input('faculty_id');

        // Faculty whose load is locked for this term — schedules/unplaced loads for
        // them must render as non-draggable on the calendar.
        $lockedFacultyIds = FacultyLoad::where('academic_term_id', $termId)
            ->where('is_locked', true)
            ->pluck('user_id')
            ->all();

        $query = ClassSchedule::with(['subject', 'classroom', 'faculty:id,name', 'section:id,sectionname,levelid'])
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
            ->when($facultyId, fn ($q) => $q->where('class_schedules.user_id', $facultyId));

        // Order by grade level + section name + day + time for clean grouping
        $dayOrder = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday')";
        $schedules = $query
            ->join('sections as sec', 'sec.id', '=', 'class_schedules.section_id')
            ->orderBy('sec.levelid')
            ->orderByRaw('sec.sectionname')
            ->orderByRaw($dayOrder)
            ->orderBy('class_schedules.start_time')
            ->select('class_schedules.*')
            ->get()
            ->map(fn ($s) => $this->mapSchedule($s, $lockedFacultyIds));

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'label'          => $t->full_label,
                'is_current'     => $t->is_current,
                'school_year_id' => $t->school_year_id,
            ]);

        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->where(fn ($q) => $q->where('on_study_leave', false)->orWhereNull('on_study_leave'))
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        $subjects   = Subject::active()->orderBy('code')->get(['id', 'code', 'name', 'subject_type', 'load_units']);
        $classrooms = Classroom::available()->orderBy('name')->get(['id', 'name', 'code', 'classroom_type', 'capacity']);

        // Sections filtered to the term's school year (fall back to all active sections)
        $syId     = $currentTerm?->school_year_id;
        $sections = Section::when($syId, fn ($q) => $q->where('school_year_id', $syId))
            ->where('is_active', true)
            ->orderBy('levelid')
            ->orderBy('sectionname')
            ->get(['id', 'sectionname', 'levelid']);

        // Per-day school config for calendar rendering (blocked periods, class hours)
        $dayConfigs = SchoolDayConfig::active()
            ->get()
            ->mapWithKeys(fn ($c) => [
                $c->day_of_week => [
                    'start'   => $c->classes_start,
                    'end'     => $c->classes_end,
                    'blocked' => $c->blocked_periods ?? [],
                ],
            ]);

        $unplacedLoads = $this->buildUnplacedLoads($termId, $sectionId, $facultyId, $lockedFacultyIds, $sections);

        return Inertia::render('FacultyLoading/Schedules/Index', [
            'schedules'     => $schedules,
            'terms'         => $terms,
            'faculty'       => $faculty,
            'subjects'      => $subjects,
            'classrooms'    => $classrooms,
            'sections'      => $sections,
            'currentTerm'   => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'       => $request->only(['term_id', 'section_id', 'faculty_id']),
            'dayConfigs'    => $dayConfigs,
            'unplacedLoads' => $unplacedLoads,
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
        $this->authorize('faculty_loading.manage');

        $data = $request->validate([
            'faculty_id'       => 'required|integer',
            'subject_id'       => 'required|integer',
            'section_id'       => 'required|integer',
            'classroom_id'     => 'required|integer',
            'academic_term_id' => 'required|integer',
            'day_of_week'      => 'required|string',
            'start_time'       => 'required|string',
            'end_time'         => 'required|string',
            'exclude_id'       => 'nullable|integer',
        ]);

        $result = $this->validation->validate($data, $data['exclude_id'] ?? null);

        return response()->json($result);
    }

    public function store(Request $request): RedirectResponse
    {
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

    public function update(Request $request, ClassSchedule $classSchedule): RedirectResponse
    {
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

    public function destroy(ClassSchedule $classSchedule): RedirectResponse
    {
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

    private function mapSchedule(ClassSchedule $s, array $lockedFacultyIds = []): array
    {
        return [
            'id'                 => $s->id,
            'load_assignment_id' => $s->load_assignment_id,
            'school_year_id'     => $s->school_year_id,
            'day_of_week'        => $s->day_of_week,
            'start_time'         => $s->start_time,
            'end_time'           => $s->end_time,
            'status'             => $s->status,
            'remarks'            => $s->remarks,
            'subject'            => $s->subject ? [
                'id'          => $s->subject->id,
                'code'        => $s->subject->code,
                'name'        => $s->subject->name,
                'is_elective' => $s->subject->grade_level === 0 || $s->subject->subject_type === 'elective',
            ] : null,
            'classroom'    => $s->classroom ? [
                'id'   => $s->classroom->id,
                'name' => $s->classroom->name,
                'code' => $s->classroom->code,
            ] : null,
            'faculty'      => $s->faculty ? [
                'id'   => $s->faculty->id,
                'name' => $s->faculty->name,
            ] : null,
            'section_id'   => $s->section_id,
            'section_name' => $s->section?->sectionname ?? "Section {$s->section_id}",
            'grade_level'  => $s->section?->levelid ?? null,
            'is_locked'    => in_array((int) $s->user_id, $lockedFacultyIds, true),
        ];
    }
}
