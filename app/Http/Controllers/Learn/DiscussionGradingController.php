<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Learn\DiscussionGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionGradingController extends Controller
{
    /** GET /learn/discussions/{discussion}/grades */
    public function index(Discussion $discussion): Response
    {
        $user = Auth::user();
        abort_unless($discussion->canEdit($user), 403);

        $course = $discussion->course();
        abort_if(! $course, 404);

        $studentIds = StudentEnrollment::where('school_year_id', $course->school_year_id)
            ->where('section_id', $course->section_id)
            ->where('status', 'enrolled')
            ->pluck('student_id')
            ->unique()
            ->values();

        $students = DB::table('students')->whereIn('id', $studentIds)->get(['id', 'lastname', 'firstname'])->keyBy('id');
        $grades = $discussion->grades()->whereIn('student_id', $studentIds)->get()->keyBy('student_id');

        $roster = $studentIds->map(function ($studentId) use ($students, $grades) {
            $student = $students->get($studentId);
            $grade = $grades->get($studentId);

            return [
                'student_id' => (int) $studentId,
                'name' => $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$studentId}",
                'points_earned' => $grade?->points_earned !== null ? (float) $grade->points_earned : null,
                'feedback_comment' => $grade?->feedback_comment,
            ];
        })->sortBy('name')->values();

        return Inertia::render('Learn/DiscussionGrading', [
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'max_score' => $discussion->maxScore(),
            ],
            'roster' => $roster,
        ]);
    }

    /** PUT /learn/discussions/{discussion}/grades/{student} */
    public function grade(Request $request, Discussion $discussion, Student $student)
    {
        $user = Auth::user();
        abort_unless($discussion->canEdit($user), 403);
        abort_if($discussion->points_possible === null, 422, 'This discussion is not graded.');

        $validated = $request->validate([
            'points_earned' => 'required|numeric|min:0|max:' . $discussion->points_possible,
            'feedback_comment' => 'nullable|string',
        ]);

        DiscussionGrade::updateOrCreate(
            ['learn_discussion_id' => $discussion->id, 'student_id' => $student->id],
            [
                'points_earned' => $validated['points_earned'],
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'graded_at' => now(),
                'graded_by' => $user->id,
            ]
        );

        return back()->with('success', 'Grade saved.');
    }
}
