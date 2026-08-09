<?php

namespace App\Services\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use Illuminate\Validation\ValidationException;

class QuizAttemptService
{
    public function start(Quiz $quiz, int $studentId): QuizAttempt
    {
        $inProgress = QuizAttempt::where('learn_quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->whereNull('submitted_at')
            ->first();
        if ($inProgress) {
            return $inProgress;
        }

        $attemptsUsed = QuizAttempt::where('learn_quiz_id', $quiz->id)->where('student_id', $studentId)->count();

        if ($quiz->max_attempts !== null && $attemptsUsed >= $quiz->max_attempts) {
            throw ValidationException::withMessages([
                'quiz' => 'You have used all of your attempts for this quiz.',
            ]);
        }

        return QuizAttempt::create([
            'learn_quiz_id' => $quiz->id,
            'student_id' => $studentId,
            'attempt_number' => $attemptsUsed + 1,
            'question_order' => $this->buildQuestionOrder($quiz),
            'started_at' => now(),
        ]);
    }

    /**
     * Samples questions_to_draw questions at random (or uses every question when unset),
     * keeping the sampled subset in original position order — then shuffles that final order
     * if shuffle_questions is set. Sampling and shuffling are independent: a drawn-but-unshuffled
     * quiz still presents its random subset in a stable, predictable order.
     *
     * @return array<int, int>
     */
    private function buildQuestionOrder(Quiz $quiz): array
    {
        $allQuestionIds = $quiz->questions()->pluck('id')->all(); // already position-ordered

        if ($quiz->questions_to_draw !== null && $quiz->questions_to_draw < count($allQuestionIds)) {
            $sampled = $allQuestionIds;
            shuffle($sampled);
            $sampled = array_slice($sampled, 0, $quiz->questions_to_draw);
            $questionIds = array_values(array_intersect($allQuestionIds, $sampled));
        } else {
            $questionIds = $allQuestionIds;
        }

        if ($quiz->shuffle_questions) {
            shuffle($questionIds);
        }

        return $questionIds;
    }
}
