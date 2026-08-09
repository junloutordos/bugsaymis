<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuizGradingController extends Controller
{
    public function __construct(private ClassRecordPushService $pushService)
    {
    }

    /** GET /learn/quizzes/{quiz}/attempts */
    public function index(Quiz $quiz): Response
    {
        $user = Auth::user();
        abort_unless($quiz->canEdit($user), 403);

        $attempts = QuizAttempt::where('learn_quiz_id', $quiz->id)
            ->with('answers.question.options', 'answers.selectedOptions')
            ->orderBy('student_id')
            ->orderBy('attempt_number')
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $attempts->pluck('student_id')->unique())
            ->get(['id', 'firstname', 'lastname'])
            ->keyBy('id');

        $quiz->load(['classRecordAssessment.gradingCategory', 'classRecordAssessment.quarter.classRecord']);
        $classRecordOptions = $this->pushService->candidateClassRecords($quiz);

        return Inertia::render('Learn/QuizGrading', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'max_score' => $quiz->maxScore(),
                'class_record_link' => $quiz->classRecordAssessment ? [
                    'assessment_id' => $quiz->classRecordAssessment->id,
                    'assessment_title' => $quiz->classRecordAssessment->title,
                    'class_record_name' => $quiz->classRecordAssessment->quarter->classRecord->display_name,
                    'quarter' => $quiz->classRecordAssessment->quarter->quarter,
                    'category_name' => $quiz->classRecordAssessment->gradingCategory->name,
                    'max_score' => (float) $quiz->classRecordAssessment->max_score,
                    'pushed_at' => $quiz->pushed_at?->toIso8601String(),
                ] : null,
                'class_record_options' => $classRecordOptions->map(fn ($cr) => [
                    'id' => $cr->id,
                    'display_name' => $cr->display_name,
                    'quarters' => $cr->quarters->map(fn ($q) => [
                        'id' => $q->id,
                        'quarter' => $q->quarter,
                        'assessments' => $q->assessments->map(fn ($a) => [
                            'id' => $a->id, 'title' => $a->title, 'max_score' => (float) $a->max_score,
                            'category_name' => $a->gradingCategory->name,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ],
            'attempts' => $attempts->map(function (QuizAttempt $attempt) use ($students) {
                $student = $students->get($attempt->student_id);

                $answers = $attempt->answers->map(fn ($answer) => [
                    'id' => $answer->id,
                    'question_type' => $answer->question->question_type,
                    'prompt' => $answer->question->prompt,
                    'points_possible' => (float) $answer->question->points,
                    'answer_text' => $answer->answer_text,
                    'selected_option_ids' => $answer->selectedOptions->pluck('learn_quiz_question_option_id')->values(),
                    'options' => in_array($answer->question->question_type, ['multiple_choice', 'true_false', 'multiple_select'], true)
                        ? $answer->question->options->map(fn ($o) => [
                            'id' => $o->id, 'option_text' => $o->option_text, 'is_correct' => $o->is_correct,
                        ])->values()
                        : null,
                    'is_correct' => $answer->is_correct,
                    'points_earned' => $answer->points_earned !== null ? (float) $answer->points_earned : null,
                ])->values();

                $pendingEssays = $answers->filter(fn ($a) => $a['question_type'] === 'essay' && $a['points_earned'] === null)->count();

                return [
                    'id' => $attempt->id,
                    'student_name' => $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$attempt->student_id}",
                    'attempt_number' => $attempt->attempt_number,
                    'is_submitted' => $attempt->isSubmitted(),
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'pending_essays' => $pendingEssays,
                    'answers' => $answers,
                ];
            })->values(),
        ]);
    }

    /** PUT /learn/quiz-attempt-answers/{answer}/grade */
    public function gradeEssay(Request $request, QuizAttemptAnswer $answer)
    {
        $user = Auth::user();
        $quiz = $answer->attempt->quiz;
        abort_unless($quiz->canEdit($user), 403);
        abort_unless($answer->question->question_type === 'essay', 422, 'Only essay answers are manually graded.');
        abort_unless($answer->attempt->isSubmitted(), 422, 'This attempt has not been submitted yet.');

        $validated = $request->validate([
            'points_earned' => 'required|numeric|min:0|max:' . $answer->question->points,
        ]);

        $answer->update([
            'points_earned' => $validated['points_earned'],
            'is_correct' => (float) $validated['points_earned'] === (float) $answer->question->points,
            'graded_at' => now(),
            'graded_by' => $user->id,
        ]);

        $this->recomputeAttemptScoreIfComplete($answer->attempt);

        return back()->with('success', 'Essay graded.');
    }

    /** POST /learn/quiz-attempts/{attempt}/reopen */
    public function reopen(QuizAttempt $attempt)
    {
        $user = Auth::user();
        abort_unless($attempt->quiz->canEdit($user), 403);

        $attempt->answers()->update(['is_correct' => null, 'points_earned' => null, 'graded_at' => null, 'graded_by' => null]);
        $attempt->update(['submitted_at' => null, 'auto_submitted' => false, 'score' => null]);

        return back()->with('success', 'Attempt reopened for resubmission.');
    }

    private function recomputeAttemptScoreIfComplete(QuizAttempt $attempt): void
    {
        $answers = $attempt->answers()->get();
        if ($answers->contains(fn ($a) => $a->points_earned === null)) {
            return;
        }

        $attempt->update(['score' => $answers->sum('points_earned')]);
    }
}
