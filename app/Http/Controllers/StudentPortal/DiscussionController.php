<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Student;
use App\Services\Learn\DiscussionPostService;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionController extends Controller
{
    public function __construct(private DiscussionPostService $postService)
    {
    }

    /** GET /student-portal/learn/discussions/{discussion} */
    public function show(Discussion $discussion): Response
    {
        $student = $this->currentStudent();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        return Inertia::render('StudentPortal/Learn/Discussion', [
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'prompt' => $discussion->prompt,
                'max_score' => $discussion->maxScore(),
            ],
            'posts' => $this->postService->tree($discussion),
            'current_student_id' => $student->id,
        ]);
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
