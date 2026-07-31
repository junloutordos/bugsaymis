<?php

namespace App\Http\Controllers\HomeroomAttendance;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\Section;
use App\Services\HomeroomAttendance\DailyAttendanceService;
use App\Services\HomeroomAttendance\RosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DailyAttendanceController extends Controller
{
    public function __construct(
        private RosterService $roster,
        private DailyAttendanceService $attendance,
    ) {}

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

        // Read-only visibility only — the adviser cannot set or clear this.
        // Cut Class (CIM 3.6/3.6.2) can only be witnessed and asserted by the
        // subject teacher on the Class Record Attendance grid; this just
        // surfaces what's already been marked there so the adviser isn't
        // blind to it for their own advisory students.
        $cuttingSubjectsByStudent = DB::table('class_record_attendance_records as car')
            ->join('class_record_attendance_dates as cad', 'cad.id', '=', 'car.class_record_attendance_date_id')
            ->join('class_record_students as crs', 'crs.id', '=', 'car.class_record_student_id')
            ->join('class_record_quarters as crq', 'crq.id', '=', 'crs.class_record_quarter_id')
            ->join('class_records as cr', 'cr.id', '=', 'crq.class_record_id')
            // cad.subject_id scopes the shared PEHM case (PE/Health/Music each
            // have their own subject-scoped dates); cr.subject_id covers a
            // normal single-subject record where cad.subject_id is null.
            ->leftJoin('subjects', 'subjects.id', '=', DB::raw('COALESCE(cad.subject_id, cr.subject_id)'))
            ->where('cr.section_id', $sectionId)
            ->where('cad.date', $date)
            ->where('car.status', 'cut_class')
            ->select('crs.student_id', 'subjects.name as subject_name', 'car.excused_status')
            ->get()
            ->groupBy('student_id');

        // "Who flagged Incomplete Uniform today" — per student, which
        // subject(s) synced the IU flag (read-only annotation next to the
        // adviser's own checkbox; see SubjectAttendanceSyncService).
        $iuSubjectsByStudent = DB::table('class_record_attendance_records as car')
            ->join('class_record_attendance_dates as cad', 'cad.id', '=', 'car.class_record_attendance_date_id')
            ->join('class_record_students as crs', 'crs.id', '=', 'car.class_record_student_id')
            ->join('class_record_quarters as crq', 'crq.id', '=', 'crs.class_record_quarter_id')
            ->join('class_records as cr', 'cr.id', '=', 'crq.class_record_id')
            ->leftJoin('subjects', 'subjects.id', '=', DB::raw('COALESCE(cad.subject_id, cr.subject_id)'))
            ->where('cr.section_id', $sectionId)
            ->where('cad.date', $date)
            ->where('car.incomplete_uniform', true)
            ->select('crs.student_id', 'subjects.name as subject_name')
            ->get()
            ->groupBy('student_id');

        // Subject-submission visibility: for every subject actually
        // scheduled to meet this section on this day of the week, has that
        // subject's Class Record Attendance grid been saved for this date?
        // Informational only — the adviser cannot act on this, it's purely
        // "who's submitted vs who hasn't yet" so they know the picture is
        // still incomplete before relying on synced Cut Class/IU flags.
        $subjectSubmissions = $this->attendance->subjectSubmissionStatus($sectionId, $date);

        $roster = $students->map(function ($student) use ($recordsByStudent, $cuttingSubjectsByStudent, $iuSubjectsByStudent) {
            $record = $recordsByStudent->get($student->id);
            $cuttingSubjects = $cuttingSubjectsByStudent->get($student->id, collect())
                ->where('excused_status', '<>', 'excused')
                ->pluck('subject_name')
                ->filter()
                ->values();
            $excusedCuttingSubjects = $cuttingSubjectsByStudent->get($student->id, collect())
                ->where('excused_status', 'excused')
                ->pluck('subject_name')
                ->filter()
                ->values();
            $iuSubjects = $iuSubjectsByStudent->get($student->id, collect())
                ->pluck('subject_name')
                ->filter()
                ->values();

            return [
                'student_id' => $student->id,
                'name' => trim("{$student->lastname}, {$student->firstname} {$student->middlename}"),
                'sex' => $student->sex,
                'status' => $record->status ?? 'present',
                'incomplete_uniform' => $record->incomplete_uniform ?? false,
                'excused_status' => $record->excused_status ?? 'n_a',
                'remarks' => $record->remarks ?? null,
                'cut_class_subjects' => $cuttingSubjects,
                'excused_cut_class_subjects' => $excusedCuttingSubjects,
                'iu_flagged_subjects' => $iuSubjects,
            ];
        });

        return Inertia::render('HomeroomAttendance/Daily', [
            'sections' => $sections->map(fn (Section $s) => ['id' => $s->id, 'name' => $s->sectionname, 'level' => $s->levelid]),
            'sectionId' => $sectionId,
            'date' => $date,
            'roster' => $roster->values(),
            'schoolYear' => $sy->only(['id', 'name']),
            'subjectSubmissions' => $subjectSubmissions,
        ]);
    }

    // ── POST /homeroom-attendance ──────────────────────────────────────────────

    public function store(Request $request)
    {
        $user = Auth::user();
        $sy = $this->roster->currentSchoolYear();
        $term = $this->roster->currentAcademicTerm($sy);

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.student_id' => ['required', 'integer'],
            'rows.*.status' => ['required', 'in:present,absent,tardy'],
            'rows.*.incomplete_uniform' => ['sometimes', 'boolean'],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
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
