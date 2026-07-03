<?php

namespace App\Services\Quiz;

use App\Models\Quiz\QuizPlayer;
use App\Models\Quiz\QuizPlayerAnswer;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizSession;
use Illuminate\Support\Facades\DB;

class QuizScoringService
{
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

            $points = $isCorrect === true
                ? $this->computeSpeedPoints($question->points_base, $responseTimeMs, $question->time_limit_seconds)
                : 0;

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

            if ($points > 0) {
                $player->increment('total_score', $points);
            }

            return $answer;
        });
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
