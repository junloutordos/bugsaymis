<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizPlayer;
use App\Models\Quiz\QuizSession;
use App\Services\Quiz\QuizSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QuizSessionController extends Controller
{
    public function __construct(private QuizSessionService $sessionService) {}

    public function store(Request $request, Quiz $quiz)
    {
        $this->authorizeHost($quiz->owner_id);
        abort_if($quiz->questions()->count() === 0, 422, 'Add at least one question before hosting.');

        $requireRosterJoin = (bool) $request->boolean('require_roster_join');
        $session = $this->sessionService->createSession($quiz, Auth::user(), $requireRosterJoin);

        return redirect()->route('quiz.sessions.show', $session);
    }

    public function show(QuizSession $session)
    {
        $this->authorizeHost($session->host_user_id);
        $session->load(['quiz.questions.options', 'players']);

        return Inertia::render('Quiz/Host', [
            'session' => [
                'id' => $session->id,
                'game_pin' => $session->game_pin,
                'status' => $session->status,
                'require_roster_join' => $session->require_roster_join,
                'current_question_index' => $session->current_question_index,
                'quiz' => [
                    'id' => $session->quiz->id,
                    'title' => $session->quiz->title,
                    'question_count' => $session->quiz->questions->count(),
                ],
                'players' => $session->players->map(fn ($p) => [
                    'id' => $p->id,
                    'nickname' => $p->nickname,
                    'total_score' => $p->total_score,
                ]),
            ],
            'report_url' => route('quiz.sessions.report', $session),
            'join_url' => route('quiz.join.pin', $session->game_pin),
            'qr_svg' => (string) QrCode::format('svg')->size(220)->margin(1)->generate(route('quiz.join.pin', $session->game_pin)),
        ]);
    }

    // start/endQuestion/leaderboard/next are triggered via axios (not Inertia
    // router) from the live Host screen — the UI is driven entirely by the
    // Echo broadcasts these actions fire, so a plain JSON ack is enough and
    // avoids an Inertia page reload wiping the screen's live-accumulated state.

    public function start(QuizSession $session)
    {
        $this->authorizeHost($session->host_user_id);
        $this->sessionService->startSession($session);

        return response()->json(['success' => true]);
    }

    public function endQuestion(QuizSession $session)
    {
        $this->authorizeHost($session->host_user_id);
        $this->sessionService->endQuestion($session);

        return response()->json(['success' => true]);
    }

    public function leaderboard(QuizSession $session)
    {
        $this->authorizeHost($session->host_user_id);
        $this->sessionService->showLeaderboard($session);

        return response()->json(['success' => true]);
    }

    public function next(QuizSession $session)
    {
        $this->authorizeHost($session->host_user_id);
        $this->sessionService->nextQuestion($session);

        return response()->json(['success' => true]);
    }

    public function end(QuizSession $session)
    {
        $this->authorizeHost($session->host_user_id);
        $this->sessionService->endSession($session);

        return redirect()->route('quiz.edit', $session->quiz_id)->with('success', 'Session ended.');
    }

    public function kickPlayer(QuizSession $session, QuizPlayer $player)
    {
        $this->authorizeHost($session->host_user_id);
        abort_unless($player->session_id === $session->id, 404);

        $this->sessionService->kickPlayer($session, $player);

        return response()->json(['success' => true]);
    }

    public function report(QuizSession $session)
    {
        $this->authorizeHost($session->host_user_id);
        $session->load(['quiz.questions.options', 'players.answers']);

        $answers = $session->answers()->get()->groupBy('question_id');
        $players = $session->players()->orderByDesc('total_score')->get();

        $questions = $session->quiz->questions->map(function ($q) use ($answers, $players) {
            $qAnswers = $answers->get($q->id, collect());
            $correct = $qAnswers->where('is_correct', true)->count();

            return [
                'id' => $q->id,
                'type' => $q->type,
                'question_text' => $q->question_text,
                'points_base' => $q->points_base,
                'double_points' => $q->double_points,
                'is_scored' => $q->isScored(),
                'answered_count' => $qAnswers->count(),
                'correct_count' => $correct,
                'accuracy_pct' => $q->isScored() && $players->count() > 0
                    ? (int) round($correct / $players->count() * 100)
                    : null,
                'avg_response_ms' => $qAnswers->count() > 0 ? (int) $qAnswers->avg('response_time_ms') : null,
                'options' => $q->options->map(fn ($o) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                    'is_correct' => $o->is_correct,
                    'count' => $qAnswers->filter(fn ($a) => in_array($o->id, $a->selected_option_ids ?? [], true))->count(),
                ])->values(),
                'answer_texts' => $q->type === 'open_ended'
                    ? $qAnswers->pluck('answer_text')->filter()->values()
                    : [],
            ];
        })->values();

        $scoredQuestionCount = $session->quiz->questions->filter(fn ($q) => $q->isScored())->count();

        return Inertia::render('Quiz/Report', [
            'session' => [
                'id' => $session->id,
                'game_pin' => $session->game_pin,
                'status' => $session->status,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
                'quiz' => ['id' => $session->quiz->id, 'title' => $session->quiz->title],
            ],
            'questions' => $questions,
            'players' => $players->values()->map(fn ($p, $i) => [
                'rank' => $i + 1,
                'nickname' => $p->nickname,
                'total_score' => $p->total_score,
                'best_streak' => $p->best_streak,
                'correct_count' => $p->answers->where('is_correct', true)->count(),
                'answered_count' => $p->answers->count(),
                'avg_response_ms' => $p->answers->count() > 0 ? (int) $p->answers->avg('response_time_ms') : null,
            ]),
            'scored_question_count' => $scoredQuestionCount,
        ]);
    }

    private function authorizeHost(int $hostUserId): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $hostUserId === $user->id, 403);
    }
}
