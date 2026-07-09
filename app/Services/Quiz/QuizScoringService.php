<?php

namespace App\Services\Quiz;

use App\Models\Quiz\QuizPlayer;
use App\Models\Quiz\QuizPlayerAnswer;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizSession;
use Illuminate\Support\Facades\DB;

class QuizScoringService
{
    // Kahoot-style streak bonus: +50 per consecutive correct answer beyond the
    // first, capped at +250 (i.e. maxes out on the 6th correct in a row).
    public const STREAK_BONUS_STEP = 50;
    public const STREAK_BONUS_CAP = 250;

    /**
     * First answer wins — a resubmission for an already-answered question is
     * returned as-is rather than rescored, so players can't farm points by
     * spamming submit while their first attempt has already locked in.
     */
    public function submitAnswer(
        QuizSession $session,
        QuizPlayer $player,
        QuizQuestion $question,
        array $selectedOptionIds,
        ?string $answerText,
        int $responseTimeMs
    ): QuizPlayerAnswer {
        return DB::transaction(function () use ($session, $player, $question, $selectedOptionIds, $answerText, $responseTimeMs) {
            $existing = QuizPlayerAnswer::where('player_id', $player->id)
                ->where('question_id', $question->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $isCorrect = $question->isScored()
                ? $this->isCorrectAnswer($question, $selectedOptionIds)
                : null;

            // Unscored questions (poll/open-ended) leave the streak untouched.
            $streak = $player->current_streak;
            if ($isCorrect === true) {
                $streak++;
            } elseif ($isCorrect === false) {
                $streak = 0;
            }

            $points = 0;
            if ($isCorrect === true) {
                $points = $this->computeSpeedPoints($question->points_base, $responseTimeMs, $question->time_limit_seconds)
                    + $this->streakBonus($streak);

                if ($question->double_points) {
                    $points *= 2;
                }
            }

            $answer = QuizPlayerAnswer::create([
                'session_id' => $session->id,
                'player_id' => $player->id,
                'question_id' => $question->id,
                'selected_option_ids' => $selectedOptionIds,
                'answer_text' => $answerText,
                'is_correct' => $isCorrect,
                'points_awarded' => $points,
                'response_time_ms' => $responseTimeMs,
                'answered_at' => now(),
            ]);

            $player->update([
                'total_score' => $player->total_score + $points,
                'current_streak' => $streak,
                'best_streak' => max($player->best_streak, $streak),
            ]);

            return $answer;
        });
    }

    protected function streakBonus(int $streak): int
    {
        return min(max($streak - 1, 0) * self::STREAK_BONUS_STEP, self::STREAK_BONUS_CAP);
    }

    protected function isCorrectAnswer(QuizQuestion $question, array $selectedOptionIds): bool
    {
        $correctIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->all();
        $selected = collect($selectedOptionIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $correctIds === $selected;
    }

    protected function computeSpeedPoints(int $pointsBase, int $responseTimeMs, int $timeLimitSeconds): int
    {
        $timeLimitMs = max($timeLimitSeconds * 1000, 1);
        $ratio = min($responseTimeMs / $timeLimitMs, 1.0);

        // Kahoot-style: full points for an instant answer, decaying to 50% of
        // base at the time limit — never below half credit for a correct answer.
        return (int) floor($pointsBase * (1 - ($ratio * 0.5)));
    }
}
