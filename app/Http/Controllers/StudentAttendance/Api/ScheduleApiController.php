<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\StudentAttendance\ParentContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleApiController extends Controller
{
    /**
     * GET /api/mobile/students/{studentId}/schedule
     *
     * Returns the student's class schedule for the current school year,
     * grouped by day of week. Only accessible by a linked parent.
     */
    public function show(Request $request, int $studentId): JsonResponse
    {
        $parentContact = ParentContact::where('user_id', $request->user()->id)->firstOrFail();

        $linked = DB::table('student_parent_contact')
            ->where('parent_contact_id', $parentContact->id)
            ->where('student_id', $studentId)
            ->exists();

        if (! $linked) {
            return response()->json(['message' => 'Student not linked to your account.'], 403);
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

        // Remove days with no classes
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
}
