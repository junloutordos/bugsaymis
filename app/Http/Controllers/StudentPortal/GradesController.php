<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentAnnualGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradesController extends Controller
{
    /**
     * GET /student-portal/grades
     *
     * Shows the authenticated student's grades for the current (or selected) school year.
     */
    public function index(Request $request): Response
    {
        $pisaysystemID = session('student_pisaysystemID');
        $student       = Student::where('pisaysystemID', $pisaysystemID)->firstOrFail();

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);
        $currentId   = $schoolYears->firstWhere('is_current', true)?->id
            ?? $schoolYears->first()?->id;

        $schoolYearId = (int) $request->input('school_year_id', $currentId);
        $selectedSY   = $schoolYears->firstWhere('id', $schoolYearId);

        // Current enrollment
        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->where('school_year_id', $schoolYearId)
            ->with('section:id,sectionname,levelid,adviser')
            ->first();

        // Annual grades
        $grades = StudentAnnualGrade::where('student_id', $student->id)
            ->where('school_year_id', $schoolYearId)
            ->whereNotNull('final_ge')
            ->orderBy('subject_name')
            ->get()
            ->map(fn ($g) => [
                'subject_name' => $g->subject_name,
                'q1_ge'        => $g->q1_ge,
                'q2_ge'        => $g->q2_ge,
                'q3_ge'        => $g->q3_ge,
                'q4_ge'        => $g->q4_ge,
                'final_ge'     => $g->final_ge,
                'remarks'      => $g->remarks,
                'passed'       => $g->isPassed(),
            ]);

        // Academic standing
        $standing = StudentAcademicStanding::where('student_id', $student->id)
            ->where('school_year_id', $schoolYearId)
            ->first();

        return Inertia::render('StudentPortal/Grades', [
            'student'     => [
                'id'        => $student->id,
                'full_name' => $student->full_name,
                'pisays_id' => $student->pisaysystemID,
            ],
            'schoolYears'       => $schoolYears,
            'selectedSchoolYear'=> $schoolYearId,
            'selectedSYName'    => $selectedSY?->name,
            'enrollment'        => $enrollment ? [
                'status'       => $enrollment->status,
                'grade_level'  => $enrollment->grade_level,
                'section_name' => $enrollment->section?->sectionname,
                'section_id'   => $enrollment->section_id,
            ] : null,
            'grades'   => $grades,
            'standing' => $standing ? [
                'gwa'                  => $standing->gwa,
                'subject_count'        => $standing->subject_count,
                'failed_subject_count' => $standing->failed_subject_count,
                'honors'               => $standing->honors,
                'standing'             => $standing->standing,
            ] : null,
            'reportCardUrl' => $enrollment
                ? route('registrar.report-card', ['student' => $student->id, 'schoolYear' => $schoolYearId])
                : null,
        ]);
    }
}
