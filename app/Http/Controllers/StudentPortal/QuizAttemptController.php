<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Models\Student;
use App\Services\Learn\QuizAttemptService;

class QuizAttemptController extends Controller
{
    public function __construct(private QuizAttemptService $attemptService)
    {
    }

    /** POST /student-portal/learn/quizzes/{quiz}/attempts */
    public function start(Quiz $quiz)
    {
        $student = $this->currentStudent();
        $course = $quiz->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $attempt = $this->attemptService->start($quiz, $student->id);

        return redirect()->route('student-portal.learn.quiz-attempts.show', $attempt);
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
