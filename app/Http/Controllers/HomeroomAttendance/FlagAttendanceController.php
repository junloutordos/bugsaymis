<?php

namespace App\Http\Controllers\HomeroomAttendance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Section;
use App\Models\HomeroomAttendance\FlagAttendanceDate;
use App\Models\User;
use App\Services\HomeroomAttendance\FlagAttendancePdfService;
use App\Services\HomeroomAttendance\FlagAttendanceService;
use App\Services\HomeroomAttendance\RosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FlagAttendanceController extends Controller
{
    public function __construct(
        private RosterService $roster,
        private FlagAttendanceService $attendance,
        private FlagAttendancePdfService $pdf,
    ) {
    }

    // ── GET /homeroom-attendance/flag ──────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $sections = $this->roster->accessibleSections($user, $term->id, $sy->id);
        abort_if($sections->isEmpty(), 403, 'You have no homeroom advisory sections assigned.');

        $sectionId = (int) $request->query('section', $sections->first()->id);
        abort_unless($sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        $eventType = $request->query('event_type', 'flag_ceremony');
        abort_unless(in_array($eventType, ['flag_ceremony', 'flag_retreat'], true), 422, 'Invalid event type.');

        $date = $request->query('date', now()->toDateString());

        $students = $this->roster->studentsForSection($sectionId, $sy->id);
        $attendanceDate = $this->attendance->findDate($sectionId, $eventType, $date);
        $recordsByStudent = $attendanceDate ? $attendanceDate->records->keyBy('student_id') : collect();

        $roster = $students->map(function ($student) use ($recordsByStudent) {
            $record = $recordsByStudent->get($student->id);

            return [
                'student_id' => $student->id,
                'name'       => trim("{$student->lastname}, {$student->firstname} {$student->middlename}"),
                'sex'        => $student->sex,
                'status'     => $record->status ?? 'present',
                'remarks'    => $record->remarks ?? null,
            ];
        });

        return Inertia::render('HomeroomAttendance/Flag/Index', [
            'sections'   => $sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->sectionname, 'level' => $s->levelid]),
            'sectionId'  => $sectionId,
            'eventType'  => $eventType,
            'date'       => $date,
            'roster'     => $roster->values(),
            'schoolYear' => $sy->only(['id', 'name']),
            'printUrl'   => $attendanceDate ? route('homeroom-attendance.flag.print', $attendanceDate->id) : null,
        ]);
    }

    // ── POST /homeroom-attendance/flag ─────────────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $data = $request->validate([
            'section_id'         => ['required', 'integer'],
            'event_type'         => ['required', 'in:flag_ceremony,flag_retreat'],
            'date'               => ['required', 'date'],
            'rows'               => ['required', 'array', 'min:1'],
            'rows.*.student_id'  => ['required', 'integer'],
            'rows.*.status'      => ['required', 'in:present,absent,tardy'],
            'rows.*.remarks'     => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(
            $this->roster->canAccessSection($user, (int) $data['section_id'], $term->id),
            403,
            'You do not have access to that section.',
        );

        $this->attendance->saveDay(
            (int) $data['section_id'],
            $sy->id,
            $data['event_type'],
            $data['date'],
            $user->id,
            $data['rows'],
        );

        return back()->with('success', 'Attendance saved.');
    }

    // ── GET /homeroom-attendance/flag/{date}/print ──────────────────────────────

    public function print(int $date): StreamedResponse
    {
        $attendanceDate = FlagAttendanceDate::with('section')->findOrFail($date);

        $user = Auth::user();
        $term = $this->roster->currentAcademicTerm($this->roster->currentSchoolYear());

        abort_unless(
            $this->roster->canAccessSection($user, $attendanceDate->section_id, $term->id),
            403,
            'You do not have access to that section.',
        );

        $adviser = User::find($attendanceDate->taken_by);

        return $this->pdf->render($attendanceDate, $attendanceDate->section, $adviser);
    }
}
