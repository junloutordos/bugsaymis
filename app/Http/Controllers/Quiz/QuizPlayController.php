<?php

namespace App\Http\Controllers\Quiz;

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

        return response()->json([
            'status' => $session->status,
            'total_score' => $player->total_score,
            'question' => $question ? [
                'id' => $question->id,
                'type' => $question->type,
                'time_limit_seconds' => $question->time_limit_seconds,
                'options' => $question->options->map(fn ($o) => ['id' => $o->id, 'option_text' => $o->option_text]),
            ] : null,
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
        // absolute: true — diffInMilliseconds() returns a signed value by default
        // in this Carbon version; an unsigned negative would fail the DB insert.
        $responseTimeMs = $session->question_started_at
            ? (int) now()->diffInMilliseconds($session->question_started_at, true)
            : 0;

        $answer = $this->scoringService->submitAnswer(
            $session,
            $player,
            $question,
            $data['selected_option_ids'] ?? [],
            $data['answer_text'] ?? null,
            $responseTimeMs,
        );

        return response()->json([
            'is_correct' => $answer->is_correct,
            'points_awarded' => $answer->points_awarded,
            'total_score' => $player->fresh()->total_score,
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
