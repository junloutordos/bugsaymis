<?php

namespace App\Http\Controllers\HomeroomAttendance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Section;
use App\Services\HomeroomAttendance\DailyAttendanceService;
use App\Services\HomeroomAttendance\RosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DailyAttendanceController extends Controller
{
    public function __construct(
        private RosterService $roster,
        private DailyAttendanceService $attendance,
    ) {
    }

    // ── GET /homeroom-attendance ───────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $sections = $this->roster->accessibleSections($user, $term->id, $sy->id);
        abort_if($sections->isEmpty(), 403, 'You have no homeroom advisory sections assigned.');

        $sectionId = (int) $request->query('section', $sections->first()->id);
        abort_unless($sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        $date = $request->query('date', now()->toDateString());

        $students = $this->roster->studentsForSection($sectionId, $sy->id);
        $attendanceDate = $this->attendance->findDate($sectionId, $date);
        $recordsByStudent = $attendanceDate ? $attendanceDate->records->keyBy('student_id') : collect();

        $roster = $students->map(function ($student) use ($recordsByStudent) {
            $record = $recordsByStudent->get($student->id);

            return [
                'student_id'         => $student->id,
                'name'               => trim("{$student->lastname}, {$student->firstname} {$student->middlename}"),
                'sex'                => $student->sex,
                'status'             => $record->status ?? 'present',
                'incomplete_uniform' => $record->incomplete_uniform ?? false,
                'excused_status'     => $record->excused_status ?? 'n_a',
                'remarks'            => $record->remarks ?? null,
            ];
        });

        return Inertia::render('HomeroomAttendance/Daily', [
            'sections'  => $sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->sectionname, 'level' => $s->levelid]),
            'sectionId' => $sectionId,
            'date'      => $date,
            'roster'    => $roster->values(),
            'schoolYear'=> $sy->only(['id', 'name']),
        ]);
    }

    // ── POST /homeroom-attendance ──────────────────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $data = $request->validate([
            'section_id'                       => ['required', 'integer'],
            'date'                              => ['required', 'date'],
            'rows'                               => ['required', 'array', 'min:1'],
            'rows.*.student_id'                 => ['required', 'integer'],
            'rows.*.status'                      => ['required', 'in:present,absent,tardy'],
            'rows.*.incomplete_uniform'          => ['sometimes', 'boolean'],
            'rows.*.remarks'                      => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(
            $this->roster->canAccessSection($user, (int) $data['section_id'], $term->id),
            403,
            'You do not have access to that section.',
        );

        $this->attendance->saveDay(
            (int) $data['section_id'],
            $sy->id,
            $data['date'],
            $user->id,
            $data['rows'],
        );

        return back()->with('success', 'Attendance saved.');
    }
}
