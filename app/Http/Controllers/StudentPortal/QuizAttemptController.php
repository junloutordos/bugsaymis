<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizQuestion;
use App\Models\Student;
use App\Services\Learn\QuizAttemptService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /** PUT /student-portal/learn/quiz-attempts/{attempt}/answers/{question} */
    public function answer(Request $request, QuizAttempt $attempt, QuizQuestion $question)
    {
        $student = $this->currentStudent();
        abort_unless($attempt->student_id === $student->id, 403);
        abort_unless(in_array($question->id, $attempt->question_order, true), 404);

        $attempt = $this->attemptService->finalizeIfExpired($attempt);
        abort_if($attempt->isSubmitted(), 403, 'This attempt has already been submitted.');

        $validated = $request->validate([
            'answer_text' => 'nullable|string',
            'selected_option_ids' => 'nullable|array',
            'selected_option_ids.*' => [
                'integer',
                Rule::exists('learn_quiz_question_options', 'id')->where('learn_quiz_question_id', $question->id),
            ],
        ]);

        $this->attemptService->saveAnswer($attempt, $question, $validated);

        return back()->with('success', 'Answer saved.');
    }

    /** POST /student-portal/learn/quiz-attempts/{attempt}/submit */
    public function submit(QuizAttempt $attempt)
    {
        $student = $this->currentStudent();
        abort_unless($attempt->student_id === $student->id, 403);

        $this->attemptService->submit($attempt);

        return redirect()->route('student-portal.learn.quiz-attempts.show', $attempt);
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
