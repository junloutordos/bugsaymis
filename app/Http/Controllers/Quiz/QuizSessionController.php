<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz\Quiz;
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
            'join_url' => route('quiz.join.pin', $session->game_pin),
            'qr_svg' => QrCode::format('svg')->size(220)->margin(1)->generate(route('quiz.join.pin', $session->game_pin)),
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

    private function authorizeHost(int $hostUserId): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $hostUserId === $user->id, 403);
    }
}
