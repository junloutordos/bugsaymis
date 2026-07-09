<?php

namespace App\Services\Quiz;

use App\Events\Quiz\QuizLeaderboardUpdated;
use App\Events\Quiz\QuizPlayerJoined;
use App\Events\Quiz\QuizPlayerKicked;
use App\Events\Quiz\QuizQuestionEnded;
use App\Events\Quiz\QuizQuestionStarted;
use App\Events\Quiz\QuizSessionEnded;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizPlayer;
use App\Models\Quiz\QuizSession;
use App\Models\User;
use Illuminate\Support\Str;

class QuizSessionService
{
    public function createSession(Quiz $quiz, User $host, bool $requireRosterJoin = false): QuizSession
    {
        return QuizSession::create([
            'quiz_id' => $quiz->id,
            'host_user_id' => $host->id,
            'game_pin' => $this->generatePin(),
            'status' => QuizSession::STATUS_LOBBY,
            'require_roster_join' => $requireRosterJoin,
        ]);
    }

    protected function generatePin(): string
    {
        do {
            $pin = (string) random_int(100000, 999999);
            $taken = QuizSession::where('game_pin', $pin)
                ->where('status', '<>', QuizSession::STATUS_ENDED)
                ->exists();
        } while ($taken);

        return $pin;
    }

    public function joinPlayer(QuizSession $session, string $nickname, ?int $userId = null, ?int $studentId = null): QuizPlayer
    {
        $player = QuizPlayer::create([
            'session_id' => $session->id,
            'nickname' => $nickname,
            'player_token' => (string) Str::uuid(),
            'user_id' => $userId,
            'student_id' => $studentId,
            'joined_at' => now(),
        ]);

        broadcast(new QuizPlayerJoined($session->game_pin, [
            'player' => ['id' => $player->id, 'nickname' => $player->nickname],
            'player_count' => $session->players()->count(),
        ]));

        return $player;
    }

    public function startSession(QuizSession $session): void
    {
        $session->update(['started_at' => now()]);
        $this->startQuestion($session, 0);
    }

    // "Get ready" intro — the question text shows for this long before the
    // answer tiles unlock and the clock starts (Kahoot-style reading time).
    public const GET_READY_SECONDS = 3;

    public function startQuestion(QuizSession $session, int $index): void
    {
        $questions = $session->quiz->questions;
        $question = $questions->get($index);

        if (! $question) {
            $this->endSession($session);
            return;
        }

        // question_started_at is set in the future — the countdown (and the
        // server-side response-time clock) only starts after the intro.
        $startsAt = now()->addSeconds(self::GET_READY_SECONDS);

        $session->update([
            'current_question_index' => $index,
            'status' => QuizSession::STATUS_QUESTION_ACTIVE,
            'question_started_at' => $startsAt,
        ]);

        $shuffleAnswers = (bool) ($session->quiz->settings['shuffle_answers'] ?? false);
        $options = $shuffleAnswers ? $question->options->shuffle()->values() : $question->options;

        broadcast(new QuizQuestionStarted($session->game_pin, [
            'question_index' => $index,
            'total_questions' => $questions->count(),
            'question' => [
                'id' => $question->id,
                'type' => $question->type,
                'question_text' => $question->question_text,
                'image' => $question->imageUrl(),
                'time_limit_seconds' => $question->time_limit_seconds,
                'points_base' => $question->points_base,
                'double_points' => $question->double_points,
                'options' => $options->values()->map(fn ($o, $i) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                    'tile_index' => $i,
                ])->all(),
            ],
            'server_started_at' => $startsAt->toISOString(),
            'get_ready_seconds' => self::GET_READY_SECONDS,
        ]));
    }

    public function endQuestion(QuizSession $session): void
    {
        // Idempotent — the host's Skip click, the timer expiry, and the
        // all-players-answered auto-end can race; only the first one wins.
        if ($session->status !== QuizSession::STATUS_QUESTION_ACTIVE) {
            return;
        }

        $question = $session->quiz->questions->get($session->current_question_index);
        if (! $question) {
            return;
        }

        $session->update(['status' => QuizSession::STATUS_REVEAL]);

        $answers = $question->answers()->where('session_id', $session->id)->get();

        // Not answering a scored question breaks the streak, same as a wrong
        // answer (Kahoot behavior) — otherwise sitting out preserves the bonus.
        if ($question->isScored()) {
            $session->players()
                ->whereNotIn('id', $answers->pluck('player_id'))
                ->update(['current_streak' => 0]);
        }

        $distribution = [];
        foreach ($question->options as $option) {
            $distribution[$option->id] = $answers->filter(
                fn ($a) => in_array($option->id, $a->selected_option_ids ?? [], true)
            )->count();
        }

        broadcast(new QuizQuestionEnded($session->game_pin, [
            'question_id' => $question->id,
            'correct_option_ids' => $question->isScored()
                ? $question->options->where('is_correct', true)->pluck('id')->values()->all()
                : [],
            'distribution' => $distribution,
            'answered_count' => $answers->count(),
            'total_players' => $session->players()->count(),
        ]));
    }

    public function showLeaderboard(QuizSession $session): void
    {
        $session->update(['status' => QuizSession::STATUS_LEADERBOARD]);

        broadcast(new QuizLeaderboardUpdated($session->game_pin, [
            'leaderboard' => $this->rankedPlayers($session),
        ]));
    }

    public function nextQuestion(QuizSession $session): void
    {
        $this->startQuestion($session, $session->current_question_index + 1);
    }

    public function kickPlayer(QuizSession $session, QuizPlayer $player): void
    {
        // Lobby-only — mid-game removal would corrupt scores and distributions.
        abort_unless($session->status === QuizSession::STATUS_LOBBY, 422, 'Players can only be removed from the lobby.');

        $player->delete();

        broadcast(new QuizPlayerKicked($session->game_pin, [
            'player_id' => $player->id,
            'player_count' => $session->players()->count(),
        ]));
    }

    public function endSession(QuizSession $session): void
    {
        $session->update(['status' => QuizSession::STATUS_ENDED, 'ended_at' => now()]);

        broadcast(new QuizSessionEnded($session->game_pin, [
            'podium' => array_slice($this->rankedPlayers($session), 0, 3),
        ]));
    }

    protected function rankedPlayers(QuizSession $session): array
    {
        return $session->players()->orderByDesc('total_score')->get()
            ->values()
            ->map(fn ($p, $i) => [
                'id' => $p->id,
                'nickname' => $p->nickname,
                'total_score' => $p->total_score,
                'current_streak' => $p->current_streak,
                'rank' => $i + 1,
            ])->all();
    }
}
