<?php

namespace App\Services\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\Learn\QuizQuestion;
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

    public function submit(QuizAttempt $attempt): QuizAttempt
    {
        return $this->finalize($attempt, autoSubmitted: false);
    }

    public function finalizeIfExpired(QuizAttempt $attempt): QuizAttempt
    {
        if ($attempt->isSubmitted()) {
            return $attempt;
        }

        $quiz = $attempt->quiz;
        if ($quiz->time_limit_minutes === null) {
            return $attempt;
        }

        $deadline = $attempt->started_at->copy()->addMinutes($quiz->time_limit_minutes);
        if (now()->lessThan($deadline)) {
            return $attempt;
        }

        return $this->finalize($attempt, autoSubmitted: true);
    }

    private function finalize(QuizAttempt $attempt, bool $autoSubmitted): QuizAttempt
    {
        if ($attempt->isSubmitted()) {
            return $attempt;
        }

        $quiz = $attempt->quiz;
        $questions = $quiz->questions()->whereIn('id', $attempt->question_order)->get()->keyBy('id');

        $hasPendingEssay = false;
        $total = 0.0;

        foreach ($attempt->question_order as $questionId) {
            $question = $questions->get($questionId);
            if (! $question) {
                continue;
            }

            $answer = $attempt->answers()->firstOrCreate(['learn_quiz_question_id' => $questionId]);
            $this->gradeAnswer($question, $answer);

            if ($answer->fresh()->points_earned === null) {
                $hasPendingEssay = true;
            } else {
                $total += (float) $answer->fresh()->points_earned;
            }
        }

        $attempt->update([
            'submitted_at' => $autoSubmitted
                ? $attempt->started_at->copy()->addMinutes($quiz->time_limit_minutes)
                : now(),
            'auto_submitted' => $autoSubmitted,
            'score' => $hasPendingEssay ? null : $total,
        ]);

        if (! $quiz->is_locked) {
            $quiz->update(['is_locked' => true]);
        }

        return $attempt->fresh();
    }

    private function gradeAnswer(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        match ($question->question_type) {
            'multiple_choice', 'true_false' => $this->gradeSingleSelect($question, $answer),
            'multiple_select' => $this->gradeMultiSelect($question, $answer),
            'short_answer' => $this->gradeShortAnswer($question, $answer),
            'essay' => null, // stays ungraded until an instructor scores it manually
        };
    }

    private function gradeSingleSelect(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        $selectedId = $answer->selectedOptions()->value('learn_quiz_question_option_id');
        $correctId = $question->options()->where('is_correct', true)->value('id');

        $isCorrect = $selectedId !== null && $selectedId === $correctId;
        $answer->update([
            'is_correct' => $isCorrect,
            'points_earned' => $isCorrect ? (float) $question->points : 0.0,
        ]);
    }

    private function gradeMultiSelect(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        $selectedIds = $answer->selectedOptions()->pluck('learn_quiz_question_option_id')->all();
        $correctIds = $question->options()->where('is_correct', true)->pluck('id')->all();
        $incorrectIds = $question->options()->where('is_correct', false)->pluck('id')->all();

        $correctSelected = count(array_intersect($selectedIds, $correctIds));
        $incorrectSelected = count(array_intersect($selectedIds, $incorrectIds));
        $totalCorrect = count($correctIds);

        $fraction = $totalCorrect > 0 ? max(0, $correctSelected - $incorrectSelected) / $totalCorrect : 0;
        $pointsEarned = round((float) $question->points * $fraction, 2);

        $answer->update([
            'is_correct' => $pointsEarned === round((float) $question->points, 2),
            'points_earned' => $pointsEarned,
        ]);
    }

    private function gradeShortAnswer(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        $submitted = trim(mb_strtolower((string) $answer->answer_text));
        $accepted = $question->acceptedAnswers()->pluck('answer_text')
            ->map(fn ($a) => trim(mb_strtolower($a)))
            ->all();

        $isCorrect = $submitted !== '' && in_array($submitted, $accepted, true);
        $answer->update([
            'is_correct' => $isCorrect,
            'points_earned' => $isCorrect ? (float) $question->points : 0.0,
        ]);
    }
}
