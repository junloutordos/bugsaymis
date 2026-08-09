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
use Inertia\Inertia;
use Inertia\Response;

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

    /** GET /student-portal/learn/quiz-attempts/{attempt} */
    public function show(QuizAttempt $attempt): Response
    {
        $student = $this->currentStudent();
        abort_unless($attempt->student_id === $student->id, 403);

        $attempt = $this->attemptService->finalizeIfExpired($attempt);
        $quiz = $attempt->quiz;

        $questions = $quiz->questions()->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        $answers = $attempt->answers()->with('selectedOptions')->get()->keyBy('learn_quiz_question_id');

        $orderedQuestions = collect($attempt->question_order)
            ->map(function ($id) use ($questions, $answers, $attempt) {
                $question = $questions->get($id);
                if (! $question) {
                    return null;
                }
                $answer = $answers->get($id);
                $hasOptions = in_array($question->question_type, ['multiple_choice', 'true_false', 'multiple_select'], true);

                return [
                    'id' => $question->id,
                    'question_type' => $question->question_type,
                    'prompt' => $question->prompt,
                    'points' => (float) $question->points,
                    'options' => $hasOptions
                        ? $this->attemptService->shuffledOptionsFor($attempt, $question)
                            ->map(fn ($o) => ['id' => $o->id, 'option_text' => $o->option_text])->values()
                        : null,
                    'your_answer' => $answer ? [
                        'answer_text' => $answer->answer_text,
                        'selected_option_ids' => $answer->selectedOptions->pluck('learn_quiz_question_option_id')->values(),
                        'is_correct' => $attempt->isSubmitted() ? $answer->is_correct : null,
                        'points_earned' => $attempt->isSubmitted() && $answer->points_earned !== null
                            ? (float) $answer->points_earned : null,
                    ] : null,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('StudentPortal/Learn/QuizAttempt', [
            'attempt' => [
                'id' => $attempt->id,
                'quiz_title' => $quiz->title,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'auto_submitted' => $attempt->auto_submitted,
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'max_score' => $quiz->maxScore(),
                'is_submitted' => $attempt->isSubmitted(),
                'questions' => $orderedQuestions,
            ],
        ]);
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
