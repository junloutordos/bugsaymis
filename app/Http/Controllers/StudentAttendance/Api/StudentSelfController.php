<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Models\StudentMobileLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentSelfController extends Controller
{
    /**
     * Resolve the student_id for the authenticated student user.
     * Returns null if no link exists (account not fully set up).
     */
    private function resolveStudentId(Request $request): ?int
    {
        $link = StudentMobileLink::where('user_id', $request->user()->id)->first();
        return $link?->student_id;
    }

    /**
     * GET /api/mobile/student/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $studentId = $this->resolveStudentId($request);

        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $student = DB::table('students')
            ->where('id', $studentId)
            ->first(['id', 'pisaysystemID', 'lastname', 'firstname', 'middlename', 'sex', 'student_email']);

        if (! $student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $schoolYear  = SchoolYear::where('is_current', true)->first();
        $enrollment  = $schoolYear
            ? StudentEnrollment::with('section')
                ->where('student_id', $studentId)
                ->where('school_year_id', $schoolYear->id)
                ->where('status', 'enrolled')
                ->first()
            : null;

        return response()->json([
            'student' => [
                'id'          => $student->id,
                'barcode'     => $student->pisaysystemID,
                'name'        => trim("{$student->lastname}, {$student->firstname}"),
                'sex'         => $student->sex,
                'email'       => $student->student_email,
                'grade_level' => $enrollment?->grade_level,
                'section'     => $enrollment?->section
                    ? $enrollment->section->sectionname
                    : null,
                'school_year' => $schoolYear?->name,
            ],
        ]);
    }

    /**
     * GET /api/mobile/student/grades
     */
    public function grades(Request $request): JsonResponse
    {
        $studentId = $this->resolveStudentId($request);

        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $schoolYear = SchoolYear::where('is_current', true)->first();

        $grades = StudentAnnualGrade::where('student_id', $studentId)
            ->when($schoolYear, fn ($q) => $q->where('school_year_id', $schoolYear->id))
            ->orderBy('subject_name')
            ->get();

        $student = DB::table('students')
            ->where('id', $studentId)
            ->first(['id', 'lastname', 'firstname']);

        return response()->json([
            'student'     => $student ? [
                'id'   => $student->id,
                'name' => trim("{$student->lastname}, {$student->firstname}"),
            ] : null,
            'school_year' => $schoolYear?->name,
            'grades'      => $grades->map(fn ($g) => [
                'subject_name' => $g->subject_name,
                'q1'           => $g->q1_ge !== null ? (float) $g->q1_ge : null,
                'q2'           => $g->q2_ge !== null ? (float) $g->q2_ge : null,
                'q3'           => $g->q3_ge !== null ? (float) $g->q3_ge : null,
                'q4'           => $g->q4_ge !== null ? (float) $g->q4_ge : null,
                'final'        => $g->final_ge !== null ? (float) $g->final_ge : null,
                'remarks'      => $g->remarks,
                'adjectival'   => $g->adjectivalLabel(),
                'is_passed'    => $g->isPassed(),
            ]),
        ]);
    }

    /**
     * GET /api/mobile/student/schedule
     */
    public function schedule(Request $request): JsonResponse
    {
        $studentId = $this->resolveStudentId($request);

        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $schoolYear = SchoolYear::where('is_current', true)->first();

        if (! $schoolYear) {
            return response()->json([
                'student'     => null,
                'school_year' => null,
                'schedule'    => [],
            ]);
        }

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYear->id)
            ->where('status', 'enrolled')
            ->first();

        $student = DB::table('students')
            ->where('id', $studentId)
            ->first(['id', 'lastname', 'firstname']);

        if (! $enrollment) {
            return response()->json([
                'student'     => $student ? [
                    'id'   => $student->id,
                    'name' => trim("{$student->lastname}, {$student->firstname}"),
                ] : null,
                'school_year' => $schoolYear->name,
                'section'     => null,
                'schedule'    => [],
            ]);
        }

        $schedules = ClassSchedule::with(['subject', 'faculty'])
            ->where('section_id', $enrollment->section_id)
            ->where('school_year_id', $schoolYear->id)
            ->active()
            ->orderByRaw("FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->orderBy('start_time')
            ->get();

        $section = DB::table('sections')
            ->where('id', $enrollment->section_id)
            ->first(['id', 'sectionname', 'levelid']);

        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $grouped  = collect($dayOrder)->mapWithKeys(fn ($day) => [$day => []]);

        foreach ($schedules as $s) {
            $day = $s->day_of_week;
            if (! isset($grouped[$day])) {
                $grouped[$day] = [];
            }
            $grouped[$day][] = [
                'id'           => $s->id,
                'subject_name' => $s->subject?->name ?? 'Unknown Subject',
                'subject_code' => $s->subject?->code,
                'teacher_name' => $s->faculty?->name,
                'start_time'   => $s->start_time,
                'end_time'     => $s->end_time,
                'duration_min' => $s->duration_minutes,
            ];
        }

        $filtered = $grouped->filter(fn ($slots) => count($slots) > 0);

        return response()->json([
            'student'     => $student ? [
                'id'   => $student->id,
                'name' => trim("{$student->lastname}, {$student->firstname}"),
            ] : null,
            'school_year' => $schoolYear->name,
            'section'     => $section ? [
                'name'        => $section->sectionname,
                'grade_level' => $section->levelid,
            ] : null,
            'schedule'    => $filtered,
        ]);
    }

    /**
     * GET /api/mobile/student/attendance
     *
     * Query params: date (YYYY-MM-DD, defaults today), per_page (max 50)
     */
    public function attendance(Request $request): JsonResponse
    {
        $studentId = $this->resolveStudentId($request);

        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $date    = $request->input('date', now()->toDateString());
        $perPage = min((int) $request->input('per_page', 20), 50);

        $paginated = StudentAttendanceLog::where('student_id', $studentId)
            ->whereDate('scan_time', $date)
            ->orderBy('scan_time')
            ->paginate($perPage);

        $student = DB::table('students')
            ->where('id', $studentId)
            ->first(['id', 'lastname', 'firstname']);

        $data = $paginated->map(fn ($log) => [
            'id'            => $log->id,
            'student_id'    => $log->student_id,
            'student_name'  => $student
                ? trim("{$student->lastname}, {$student->firstname}")
                : 'Unknown',
            'type'          => $log->type,
            'type_label'    => $log->type === 'in' ? 'Time In' : 'Time Out',
            'scan_time'     => $log->scan_time->toIso8601String(),
            'gate_location' => $log->gate_location,
            'source'        => $log->source,
        ]);

        return response()->json([
            'date'       => $date,
            'data'       => $data,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'has_more'     => $paginated->hasMorePages(),
            ],
        ]);
    }
}
