<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Models\StudentAttendance\StudentAttendanceLog;
use App\Services\SchoolCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentSelfController extends Controller
{
    /**
     * Resolve the student_id for the authenticated student.
     * The Sanctum token is issued directly against the Student model, so
     * $request->user() already IS the student — no users/mobile-link lookup.
     */
    private function resolveStudentId(Request $request): ?int
    {
        return $request->user()?->id;
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
            ->first(['id', 'pisaysystemID', 'lastname', 'firstname', 'middlename', 'sex', 'student_email', 'img']);

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
                'has_photo'   => (bool) $student->img,
                'grade_level' => $enrollment?->grade_level,
                'section'     => $enrollment?->section
                    ? $enrollment->section->sectionname
                    : null,
                'school_year' => $schoolYear?->name,
            ],
        ]);
    }

    /**
     * GET /api/mobile/student/photo
     *
     * Self-scoped mirror of StudentController::proxyPhoto — deliberately
     * takes no student-id parameter so it can only ever stream the token's
     * own photo (Sanctum tokens for students are issued directly against
     * the Student model, per resolveStudentId()).
     */
    public function photo(Request $request)
    {
        $studentId = $this->resolveStudentId($request);
        abort_if(! $studentId, 404);

        $img = DB::table('students')->where('id', $studentId)->value('img');
        abort_if(! $img, 404);

        if (str_contains($img, '/')) {
            abort_if(! Storage::disk('s3')->exists($img), 404);
            $content = Storage::disk('s3')->get($img);
            $mime = Storage::disk('s3')->mimeType($img) ?: 'image/jpeg';

            return response($content, 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        $localPath = storage_path("app/public/students_profile_picture/{$img}");
        abort_if(! file_exists($localPath), 404);

        return response()->file($localPath, ['Cache-Control' => 'private, max-age=3600']);
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
            ->classes()
            ->where('section_id', $enrollment->section_id)
            ->where('school_year_id', $schoolYear->id)
            ->occupying()
            ->orderByRaw("FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->orderBy('start_time')
            ->get();

        $section = DB::table('sections')
            ->where('id', $enrollment->section_id)
            ->first(['id', 'sectionname', 'levelid']);

        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $grouped  = array_fill_keys($dayOrder, []);
        $isProvisional = false;

        foreach ($schedules as $s) {
            $day = $s->day_of_week;
            if (! isset($grouped[$day])) {
                $grouped[$day] = [];
            }
            if ($s->status !== 'active') {
                $isProvisional = true;
            }
            $grouped[$day][] = [
                'id'           => $s->id,
                'subject_name' => $s->subject?->name ?? 'Unknown Subject',
                'subject_code' => $s->subject?->code,
                'teacher_name' => $s->faculty?->name,
                'start_time'   => $s->start_time,
                'end_time'     => $s->end_time,
                'duration_min' => $s->duration_minutes,
                'status'       => $s->status,
            ];
        }

        $filtered = array_filter($grouped, fn ($slots) => count($slots) > 0);

        return response()->json([
            'student'        => $student ? [
                'id'   => $student->id,
                'name' => trim("{$student->lastname}, {$student->firstname}"),
            ] : null,
            'school_year'    => $schoolYear->name,
            'section'        => $section ? [
                'name'        => $section->sectionname,
                'grade_level' => $section->levelid,
            ] : null,
            'schedule'       => $filtered,
            'is_provisional' => $isProvisional,
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

    /**
     * GET /api/mobile/student/attendance/summary
     */
    public function attendanceSummary(Request $request, SchoolCalendarService $calendar): JsonResponse
    {
        $studentId = $this->resolveStudentId($request);

        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $gradeLevel = $this->currentGradeLevel($studentId);
        $today = Carbon::now()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();

        [$monthPresent, $monthSchoolDays] = $this->presentAndSchoolDays(
            $studentId, $monthStart, $today, $gradeLevel, $calendar,
        );

        $weekly = [];
        for ($i = 8; $i >= 0; $i--) {
            $weekStart = $today->copy()->subWeeks($i)->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            if ($weekEnd->greaterThan($today)) {
                $weekEnd = $today->copy();
            }

            [$present, $schoolDays] = $this->presentAndSchoolDays(
                $studentId, $weekStart, $weekEnd, $gradeLevel, $calendar,
            );

            $weekly[] = [
                'week_start' => $weekStart->toDateString(),
                'present' => $present,
                'school_days' => $schoolDays,
                'rate' => $schoolDays > 0 ? round($present / $schoolDays, 4) : null,
            ];
        }

        return response()->json([
            'month_present' => $monthPresent,
            'month_school_days' => $monthSchoolDays,
            'month_rate' => $monthSchoolDays > 0 ? round($monthPresent / $monthSchoolDays, 4) : null,
            'weekly' => $weekly,
        ]);
    }

    private function currentGradeLevel(int $studentId): ?int
    {
        $schoolYear = SchoolYear::where('is_current', true)->first();
        if (! $schoolYear) {
            return null;
        }

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYear->id)
            ->where('status', 'enrolled')
            ->first();

        return $enrollment?->grade_level;
    }

    /**
     * @return array{0: int, 1: int} [present days, school days] in the
     *   inclusive [$start, $end] range.
     */
    private function presentAndSchoolDays(
        int $studentId,
        Carbon $start,
        Carbon $end,
        ?int $gradeLevel,
        SchoolCalendarService $calendar,
    ): array {
        $present = StudentAttendanceLog::where('student_id', $studentId)
            ->where('type', 'in')
            ->whereBetween('scan_time', [$start, $end->copy()->endOfDay()])
            ->get()
            ->map(fn ($log) => $log->scan_time->toDateString())
            ->unique()
            ->count();

        $schoolDays = 0;
        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            if ($calendar->isSchoolDay($date->toDateString(), $gradeLevel)) {
                $schoolDays++;
            }
        }

        return [$present, $schoolDays];
    }
}
