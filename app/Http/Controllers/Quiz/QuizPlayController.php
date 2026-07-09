<?php

namespace App\Http\Controllers\Quiz;

use App\Events\Quiz\QuizAnswerSubmitted;
use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\Quiz\QuizPlayer;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizSession;
use App\Services\Quiz\QuizScoringService;
use App\Services\Quiz\QuizSessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuizPlayController extends Controller
{
    public function __construct(
        private QuizSessionService $sessionService,
        private QuizScoringService $scoringService,
    ) {}

    public function joinForm(?string $pin = null)
    {
        return Inertia::render('Quiz/Join', ['pin' => $pin]);
    }

    /**
     * Looks up an active session by PIN — used by Join.vue before showing the
     * nickname/roster step. No auth: this is the public "kahoot.it"-style entry.
     */
    public function lookupPin(Request $request)
    {
        $data = $request->validate(['pin' => ['required', 'digits:6']]);

        $session = QuizSession::with('quiz')
            ->where('game_pin', $data['pin'])
            ->where('status', QuizSession::STATUS_LOBBY)
            ->latest('id')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'That PIN isn\'t live right now.'], 404);
        }

        return response()->json([
            'session_id' => $session->id,
            'quiz_title' => $session->quiz->title,
            'require_roster_join' => $session->require_roster_join,
            'roster' => $session->require_roster_join ? $this->rosterFor($session) : [],
        ]);
    }

    public function join(Request $request)
    {
        $data = $request->validate([
            'pin' => ['required', 'digits:6'],
            'nickname' => ['required', 'string', 'max:40'],
            'student_id' => ['nullable', 'integer'],
        ]);

        $session = QuizSession::where('game_pin', $data['pin'])
            ->where('status', QuizSession::STATUS_LOBBY)
            ->latest('id')
            ->firstOrFail();

        $player = $this->sessionService->joinPlayer(
            $session,
            $data['nickname'],
            optional($request->user())->id,
            $data['student_id'] ?? null,
        );

        return response()->json([
            'player_token' => $player->player_token,
            'play_url' => route('quiz.play', $player->player_token),
        ]);
    }

    public function play(string $playerToken)
    {
        $player = QuizPlayer::where('player_token', $playerToken)->firstOrFail();
        $player->load('session.quiz');

        return Inertia::render('Quiz/Play', [
            'player' => ['id' => $player->id, 'nickname' => $player->nickname, 'total_score' => $player->total_score],
            'session' => [
                'id' => $player->session->id,
                'game_pin' => $player->session->game_pin,
                'status' => $player->session->status,
                'quiz_title' => $player->session->quiz->title,
            ],
            'player_token' => $player->player_token,
        ]);
    }

    /**
     * Reconnect/refresh snapshot — Echo drives live updates, this just covers
     * a dropped connection or a page reload on a phone.
     */
    public function state(string $playerToken)
    {
        $player = QuizPlayer::where('player_token', $playerToken)->firstOrFail();
        $session = $player->session()->with('quiz.questions.options')->firstOrFail();

        $question = $session->status !== QuizSession::STATUS_LOBBY
            ? $session->quiz->questions->get($session->current_question_index)
            : null;

        $answered = $question
            ? $player->answers()->where('question_id', $question->id)->exists()
            : false;

        // Note: shuffle_answers order is not reproduced here — a reconnecting
        // player gets the authored order. Acceptable: the tiles are still the
        // same answers, and shuffling only matters for the initial broadcast.
        return response()->json([
            'status' => $session->status,
            'total_score' => $player->total_score,
            'current_streak' => $player->current_streak,
            'question_index' => $session->current_question_index,
            'total_questions' => $session->quiz->questions->count(),
            'question' => $question ? [
                'id' => $question->id,
                'type' => $question->type,
                'question_text' => $question->question_text,
                'image' => $question->imageUrl(),
                'time_limit_seconds' => $question->time_limit_seconds,
                'points_base' => $question->points_base,
                'double_points' => $question->double_points,
                'options' => $question->options->values()->map(fn ($o, $i) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                    'tile_index' => $i,
                ]),
            ] : null,
            'question_started_at' => $session->question_started_at?->toISOString(),
            'already_answered' => $answered,
        ]);
    }

    public function submitAnswer(Request $request, string $playerToken)
    {
        $data = $request->validate([
            'selected_option_ids' => ['nullable', 'array'],
            'answer_text' => ['nullable', 'string', 'max:500'],
        ]);

        $player = QuizPlayer::where('player_token', $playerToken)->firstOrFail();
        $session = $player->session;
        abort_unless($session->status === QuizSession::STATUS_QUESTION_ACTIVE, 422, 'No active question.');

        $question = $session->quiz->questions()->with('options')->get()->get($session->current_question_index);
        abort_unless($question, 404);

        // Server-authoritative — never trust a client-reported elapsed time.
        // Signed diff (started_at → now), clamped at 0: question_started_at sits
        // in the future during the "get ready" intro, and an unsigned negative
        // would fail the DB insert.
        $responseTimeMs = $session->question_started_at
            ? max(0, (int) $session->question_started_at->diffInMilliseconds(now()))
            : 0;

        $this->scoringService->submitAnswer(
            $session,
            $player,
            $question,
            $data['selected_option_ids'] ?? [],
            $data['answer_text'] ?? null,
            $responseTimeMs,
        );

        $answeredCount = $question->answers()->where('session_id', $session->id)->count();
        $totalPlayers = $session->players()->count();

        broadcast(new QuizAnswerSubmitted($session->game_pin, [
            'answered_count' => $answeredCount,
            'total_players' => $totalPlayers,
        ]));

        // Kahoot behavior: once everyone has locked in, don't wait out the clock.
        if ($answeredCount >= $totalPlayers) {
            $this->sessionService->endQuestion($session->fresh());
        }

        // Ack only — correctness and points stay server-side until the host
        // ends the question; the client then fetches result() on reveal.
        return response()->json(['answered' => true]);
    }

    /**
     * Reveal-gated result for the current question — refuses to answer while
     * the question is still live so a player cannot learn correctness early
     * (the pre-reveal leak this replaces returned is_correct at submit time).
     */
    public function result(string $playerToken)
    {
        $player = QuizPlayer::where('player_token', $playerToken)->firstOrFail();
        $session = $player->session;

        if ($session->status === QuizSession::STATUS_QUESTION_ACTIVE || $session->status === QuizSession::STATUS_LOBBY) {
            return response()->json(['message' => 'Results are not available yet.'], 409);
        }

        $question = $session->quiz->questions()->get()->get($session->current_question_index);
        abort_unless($question, 404);

        $answer = $player->answers()->where('question_id', $question->id)->first();

        return response()->json([
            'answered' => (bool) $answer,
            'is_correct' => $answer?->is_correct,
            'points_awarded' => $answer?->points_awarded ?? 0,
            'response_time_ms' => $answer?->response_time_ms,
            'total_score' => $player->total_score,
            'current_streak' => $player->current_streak,
        ]);
    }

    private function rosterFor($session): array
    {
        $classRecordId = $session->quiz->source_type === 'class_record' ? $session->quiz->source_id : null;
        if (!$classRecordId) {
            return [];
        }

        $quarter = ClassRecordQuarter::where('class_record_id', $classRecordId)->latest('quarter')->first();
        if (!$quarter) {
            return [];
        }

        $joinedStudentIds = $session->players()->whereNotNull('student_id')->pluck('student_id')->all();

        return $quarter->students()
            ->where('is_active', true)
            ->whereNotIn('student_id', $joinedStudentIds)
            ->get(['student_id', 'family_name', 'given_name', 'middle_initial'])
            ->map(fn ($s) => ['student_id' => $s->student_id, 'name' => $s->full_name])
            ->values()
            ->all();
    }
}
