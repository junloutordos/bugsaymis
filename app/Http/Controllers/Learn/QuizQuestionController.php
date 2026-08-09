<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizQuestion;
use App\Services\Learn\QuizQuestionFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class QuizQuestionController extends Controller
{
    public function __construct(private QuizQuestionFactory $questionFactory)
    {
    }

    private function questionValidationRules(): array
    {
        return [
            'question_type' => 'required|in:multiple_choice,true_false,multiple_select,short_answer,essay',
            'prompt' => 'required|string',
            'points' => 'required|numeric|min:0',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'options' => 'nullable|array',
            'options.*.option_text' => 'required_with:options|string|max:255',
            'options.*.is_correct' => 'nullable|boolean',
            'accepted_answers' => 'nullable|array',
            'accepted_answers.*' => 'required_with:accepted_answers|string|max:255',
        ];
    }

    private function assertPointsMatchDrawConstraint(Quiz $quiz, float $points, ?int $excludingQuestionId = null): void
    {
        if ($quiz->questions_to_draw === null) {
            return;
        }

        $existingPoints = $quiz->questions()
            ->when($excludingQuestionId, fn ($q) => $q->where('id', '!=', $excludingQuestionId))
            ->value('points');

        if ($existingPoints !== null && (float) $existingPoints !== $points) {
            throw ValidationException::withMessages([
                'points' => "This quiz draws a random question subset — every question must be worth {$existingPoints} points.",
            ]);
        }
    }

    /** POST /learn/quizzes/{quiz}/questions */
    public function store(Request $request, Quiz $quiz)
    {
        $user = Auth::user();
        abort_unless($quiz->canEdit($user), 403);

        $validated = $request->validate($this->questionValidationRules());
        $this->assertPointsMatchDrawConstraint($quiz, (float) $validated['points']);

        $position = ((int) $quiz->questions()->max('position')) + 1;
        $this->questionFactory->create($quiz, $validated, $position);

        return back()->with('success', 'Question added.');
    }

    /** PUT /learn/quiz-questions/{question} */
    public function update(Request $request, QuizQuestion $question)
    {
        $user = Auth::user();
        $quiz = $question->quiz;
        abort_unless($quiz->canEdit($user), 403);
        abort_if($quiz->is_locked, 403, 'This quiz is locked — students have already submitted attempts.');

        $validated = $request->validate($this->questionValidationRules());
        $this->assertPointsMatchDrawConstraint($quiz, (float) $validated['points'], $question->id);

        $question->update([
            'question_type' => $validated['question_type'],
            'prompt' => $validated['prompt'],
            'points' => $validated['points'],
            'difficulty' => $validated['difficulty'] ?? null,
        ]);

        $question->options()->delete();
        $question->acceptedAnswers()->delete();

        if (in_array($validated['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            foreach (($validated['options'] ?? []) as $position => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'] ?? false,
                    'position' => $position,
                ]);
            }
        } elseif ($validated['question_type'] === 'short_answer') {
            foreach (($validated['accepted_answers'] ?? []) as $answer) {
                $question->acceptedAnswers()->create(['answer_text' => $answer]);
            }
        }

        return back()->with('success', 'Question updated.');
    }

    /** DELETE /learn/quiz-questions/{question} */
    public function destroy(QuizQuestion $question)
    {
        $user = Auth::user();
        $quiz = $question->quiz;
        abort_unless($quiz->canEdit($user), 403);
        abort_if($quiz->is_locked, 403, 'This quiz is locked — students have already submitted attempts.');

        $question->delete();

        return back()->with('success', 'Question deleted.');
    }
}
