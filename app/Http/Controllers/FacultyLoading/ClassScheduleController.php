<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use App\Services\FacultyLoading\LoadComputationService;
use App\Services\FacultyLoading\ScheduleValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $facultyId   = $request->input('faculty_id');

        $query = ClassSchedule::with(['subject', 'classroom', 'faculty:id,name'])
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->when($facultyId, fn ($q) => $q->where('user_id', $facultyId));

        $schedules = $query->orderBy('day_of_week')->orderBy('start_time')->get()
            ->map(fn ($s) => $this->mapSchedule($s));

        $terms = AcademicTerm::with('schoolYear')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        $faculty = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        $subjects   = Subject::active()->orderBy('code')->get(['id', 'code', 'name', 'subject_type', 'load_units']);
        $classrooms = Classroom::available()->orderBy('name')->get(['id', 'name', 'code', 'classroom_type', 'capacity']);

        $sections = DB::table('sections')->orderBy('sectionname')->get(['id', 'sectionname', 'levelid']);

        return Inertia::render('FacultyLoading/Schedules/Index', [
            'schedules'   => $schedules,
            'terms'       => $terms,
            'faculty'     => $faculty,
            'subjects'    => $subjects,
            'classrooms'  => $classrooms,
            'sections'    => $sections,
            'currentTerm' => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'     => $request->only(['term_id', 'faculty_id']),
        ]);
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
        ]);

        $validation = $this->validation->validate($data);

        // Hard block: errors must be resolved
        if (! $validation['valid'] && ! ($request->boolean('force') && empty($validation['errors']))) {
            if (! empty($validation['errors'])) {
                return back()->withErrors($validation['errors'])->with('validation_result', $validation);
            }
        }

        $schedule = ClassSchedule::create([
            'user_id'          => $data['faculty_id'],
            'subject_id'       => $data['subject_id'],
            'section_id'       => $data['section_id'],
            'classroom_id'     => $data['classroom_id'],
            'school_year_id'   => $data['school_year_id'],
            'academic_term_id' => $data['academic_term_id'],
            'day_of_week'      => $data['day_of_week'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'status'           => $data['status'] ?? 'active',
            'remarks'          => $data['remarks'] ?? null,
            'created_by'       => Auth::id(),
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

        $validation = $this->validation->validate($data, $classSchedule->id);

        if (! $validation['valid'] && ! ($request->boolean('force') && empty($validation['errors']))) {
            if (! empty($validation['errors'])) {
                return back()->withErrors($validation['errors'])->with('validation_result', $validation);
            }
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

        $classSchedule->update(['status' => 'cancelled']);

        return back()->with('success', 'Schedule cancelled.');
    }

    private function mapSchedule(ClassSchedule $s): array
    {
        return [
            'id'          => $s->id,
            'day_of_week' => $s->day_of_week,
            'start_time'  => $s->start_time,
            'end_time'    => $s->end_time,
            'status'      => $s->status,
            'remarks'     => $s->remarks,
            'subject'     => $s->subject ? [
                'id'   => $s->subject->id,
                'code' => $s->subject->code,
                'name' => $s->subject->name,
            ] : null,
            'classroom'   => $s->classroom ? [
                'id'   => $s->classroom->id,
                'name' => $s->classroom->name,
                'code' => $s->classroom->code,
            ] : null,
            'faculty'     => $s->faculty ? [
                'id'   => $s->faculty->id,
                'name' => $s->faculty->name,
            ] : null,
            'section_id'  => $s->section_id,
        ];
    }
}
