<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAnnualGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradeApiController extends Controller
{
    /**
     * GET /api/mobile/students/{studentId}/grades
     *
     * Returns the student's annual grades for the current school year,
     * grouped by quarter. Only accessible by a parent linked to this student.
     */
    public function show(Request $request, int $studentId): JsonResponse
    {
        $parentContact = $request->user();

        $linked = DB::table('student_parent_contact')
            ->where('parent_contact_id', $parentContact->id)
            ->where('student_id', $studentId)
            ->exists();

        if (! $linked) {
            return response()->json(['message' => 'Student not linked to your account.'], 403);
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
}
