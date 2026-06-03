<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Services\Registrar\StudentTranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TranscriptController extends Controller
{
    public function __construct(
        private readonly StudentTranscriptService $transcriptService,
    ) {}

    // ── List students with transcript status ──────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('students.transcript.view');

        $currentId    = SchoolYear::where('is_current', true)->value('id');
        $schoolYearId = (int) $request->input('school_year_id', $currentId);
        $gradeFilter  = $request->input('grade_level');
        $search       = trim($request->input('q', ''));

        // Enrollments for this school year (with grade filter)
        $enrollmentQuery = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled');

        if ($gradeFilter) {
            $enrollmentQuery->where('grade_level', $gradeFilter);
        }

        $enrollments   = $enrollmentQuery->with('section:id,sectionname,levelid')->get();
        $enrolledIds   = $enrollments->pluck('student_id');

        // Load student records (MyISAM — application join)
        $studentsQuery = Student::whereIn('id', $enrolledIds);
        if ($search) {
            $studentsQuery->where(function ($q) use ($search) {
                $q->where('lastname', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('pisaysystemID', 'like', "%{$search}%");
            });
        }
        $students = $studentsQuery->orderBy('lastname')->orderBy('firstname')->get()
            ->keyBy('id');

        // Annual grade snapshot counts per student
        $gradeCounts = StudentAnnualGrade::where('school_year_id', $schoolYearId)
            ->whereIn('student_id', $enrolledIds)
            ->selectRaw('student_id, COUNT(*) as subject_count, SUM(CASE WHEN final_ge IS NOT NULL THEN 1 ELSE 0 END) as computed_count')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        // Academic standing records
        $standings = StudentAcademicStanding::where('school_year_id', $schoolYearId)
            ->whereIn('student_id', $enrolledIds)
            ->get()
            ->keyBy('student_id');

        $enrollmentMap = $enrollments->keyBy('student_id');

        $rows = $students->map(fn ($s) => [
            'id'               => $s->id,
            'full_name'        => $s->full_name,
            'pisays_id'        => $s->pisaysystemID,
            'grade_level'      => $enrollmentMap->get($s->id)?->grade_level,
            'section_name'     => $enrollmentMap->get($s->id)?->section?->sectionname,
            'subject_count'    => $gradeCounts->get($s->id)?->subject_count ?? 0,
            'computed_count'   => $gradeCounts->get($s->id)?->computed_count ?? 0,
            'gwa'              => $standings->get($s->id)?->gwa,
            'honors'           => $standings->get($s->id)?->honors ?? '—',
            'standing'         => $standings->get($s->id)?->standing ?? '—',
        ])->values()->toArray();

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        return Inertia::render('Registrar/Transcript/Index', [
            'rows'               => $rows,
            'schoolYears'        => $schoolYears,
            'selectedSchoolYear' => $schoolYearId,
            'filters'            => ['grade_level' => $gradeFilter, 'q' => $search],
            'gradeLevels'        => range(7, 12),
        ]);
    }

    // ── View one student's transcript ─────────────────────────────────────────

    public function show(Request $request, int $studentId): JsonResponse
    {
        $this->authorize('students.transcript.view');

        $schoolYearId = (int) $request->input('school_year_id',
            SchoolYear::where('is_current', true)->value('id'));

        $student = Student::findOrFail($studentId);
        $grades  = $this->transcriptService->getTranscript($studentId, $schoolYearId);
        $standing = StudentAcademicStanding::where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->first();

        return response()->json([
            'student' => [
                'id'        => $student->id,
                'full_name' => $student->full_name,
                'pisays_id' => $student->pisaysystemID,
                'lrn'       => $student->lrn,
            ],
            'grades'   => $grades,
            'standing' => $standing ? [
                'gwa'                  => $standing->gwa,
                'subject_count'        => $standing->subject_count,
                'failed_subject_count' => $standing->failed_subject_count,
                'honors'               => $standing->honors,
                'standing'             => $standing->standing,
            ] : null,
        ]);
    }

    // ── Trigger grade computation for one student ─────────────────────────────

    public function compute(Request $request): RedirectResponse
    {
        $this->authorize('students.transcript.manage');

        $data = $request->validate([
            'student_id'    => ['required', 'integer'],
            'school_year_id'=> ['required', 'integer', 'exists:school_years,id'],
        ]);

        $enrollment = StudentEnrollment::where('student_id', $data['student_id'])
            ->where('school_year_id', $data['school_year_id'])
            ->firstOrFail();

        $grades = $this->transcriptService->computeAndSave(
            $data['student_id'],
            $data['school_year_id']
        );

        $this->transcriptService->computeStanding(
            $data['student_id'],
            $data['school_year_id'],
            $enrollment->grade_level,
            Auth::id()
        );

        return back()->with('success', count($grades) . ' subject grade(s) computed.');
    }

    // ── Bulk compute for all enrolled students in a school year ──────────────

    public function bulkCompute(Request $request): RedirectResponse
    {
        $this->authorize('students.transcript.manage');

        $schoolYearId = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
        ])['school_year_id'];

        $enrollments = StudentEnrollment::where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled')
            ->get();

        $processed = 0;
        foreach ($enrollments as $enrollment) {
            $this->transcriptService->computeAndSave($enrollment->student_id, $schoolYearId);
            $this->transcriptService->computeStanding(
                $enrollment->student_id,
                $schoolYearId,
                $enrollment->grade_level,
                Auth::id()
            );
            $processed++;
        }

        return back()->with('success', "Grades computed for {$processed} enrolled student(s).");
    }

    // ── Lock grades for a school year ─────────────────────────────────────────

    public function lock(Request $request): RedirectResponse
    {
        $this->authorize('students.transcript.manage');

        $data = $request->validate([
            'student_id'    => ['required', 'integer'],
            'school_year_id'=> ['required', 'integer', 'exists:school_years,id'],
        ]);

        StudentAnnualGrade::where('student_id', $data['student_id'])
            ->where('school_year_id', $data['school_year_id'])
            ->whereNull('locked_at')
            ->update([
                'is_locked'  => true,
                'locked_by'  => Auth::id(),
                'locked_at'  => now(),
            ]);

        return back()->with('success', 'Grades locked.');
    }
}
