<?php

namespace App\Services\Learn;

use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\Learn\QuizQuestion;
use Illuminate\Support\Collection;

class QuizAnalyticsService
{
    /** @return array{questions: array, distribution: array} */
    public function itemAnalysis(Quiz $quiz): array
    {
        $submittedAttemptIds = $quiz->attempts()->whereNotNull('submitted_at')->pluck('id');

        $questionStats = $quiz->questions()->get()->map(function (QuizQuestion $question) use ($submittedAttemptIds) {
            $answers = QuizAttemptAnswer::whereIn('learn_quiz_attempt_id', $submittedAttemptIds)
                ->where('learn_quiz_question_id', $question->id)
                ->whereNotNull('points_earned')
                ->get();

            $avgPercentage = $answers->isNotEmpty() && (float) $question->points > 0
                ? round($answers->avg(fn ($a) => ((float) $a->points_earned / (float) $question->points) * 100), 1)
                : null;

            return [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'difficulty' => $question->difficulty,
                'avg_score_percentage' => $avgPercentage,
                'graded_attempts' => $answers->count(),
            ];
        })->values()->all();

        $scores = QuizAttempt::whereIn('id', $submittedAttemptIds)
            ->whereNotNull('score')
            ->pluck('score')
            ->map(fn ($s) => (float) $s);

        return [
            'questions' => $questionStats,
            'distribution' => [
                'min' => $scores->isNotEmpty() ? $scores->min() : null,
                'max' => $scores->isNotEmpty() ? $scores->max() : null,
                'avg' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                'median' => $scores->isNotEmpty() ? $this->median($scores) : null,
            ],
        ];
    }

    /** @return array{quizzes: array, by_difficulty: array} */
    public function courseTrend(Course $course): array
    {
        $quizzes = collect();
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Quiz) {
                    $quizzes->push($item->itemable);
                }
            }
        }
        $quizzes = $quizzes->sortBy(fn (Quiz $q) => $q->due_at ?? $q->created_at)->values();

        $quizTrend = $quizzes->map(function (Quiz $quiz) {
            $submittedScoredIds = $quiz->attempts()->whereNotNull('submitted_at')->whereNotNull('score')->pluck('id');
            $scores = QuizAttempt::whereIn('id', $submittedScoredIds)->pluck('score')->map(fn ($s) => (float) $s);
            $maxScore = $quiz->maxScore();

            $avgPercentage = $scores->isNotEmpty() && $maxScore
                ? round(($scores->avg() / $maxScore) * 100, 1)
                : null;

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'due_at' => $quiz->due_at?->toIso8601String(),
                'avg_score_percentage' => $avgPercentage,
            ];
        })->values()->all();

        $byDifficulty = collect(['easy', 'medium', 'hard'])->mapWithKeys(function (string $difficulty) use ($quizzes) {
            $questionIds = QuizQuestion::whereIn('learn_quiz_id', $quizzes->pluck('id'))
                ->where('difficulty', $difficulty)
                ->pluck('id');

            $answers = QuizAttemptAnswer::whereIn('learn_quiz_question_id', $questionIds)
                ->whereNotNull('points_earned')
                ->with('question')
                ->get();

            $percentages = $answers->map(function ($a) {
                $points = (float) $a->question->points;

                return $points > 0 ? ((float) $a->points_earned / $points) * 100 : null;
            })->filter(fn ($p) => $p !== null);

            return [$difficulty => $percentages->isNotEmpty() ? round($percentages->avg(), 1) : null];
        })->all();

        return ['quizzes' => $quizTrend, 'by_difficulty' => $byDifficulty];
    }

    private function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
        }

        return round($sorted[$middle], 2);
    }
}
