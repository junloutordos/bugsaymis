<?php

use App\Http\Controllers\Quiz\QuizController;
use App\Http\Controllers\Quiz\QuizPlayController;
use App\Http\Controllers\Quiz\QuizSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Quiz — live interactive quiz/poll module (Kahoot/Mentimeter-style)
|--------------------------------------------------------------------------
|
| Three trust tiers:
| - Management (auth, quiz.manage): build/host quizzes, ownership-checked
|   per-action in the controllers (mirrors AMS's authorizeEdit/authorizeManage).
| - Public play (no auth): PIN join + answer submission, same trust model
|   as AMS's public evaluation links (routes/ams.php).
| - Image proxies are public too — players must see question/cover art
|   without logging in, same as the S3-private-bucket proxy pattern used
|   elsewhere (never disk('public')).
|
| IMPORTANT — registration order: every literal-path public route below is
| registered BEFORE the /{quiz} wildcard management routes. Laravel matches
| routes in registration order and {quiz} matches any single segment — if a
| literal route like /lookup-pin were registered after POST /{quiz}, it would
| be silently swallowed by the wildcard (and inherit its auth middleware).
| Same convention as ams.php: "registered last so /{activity} never shadows
| specific routes."
|
*/

// ── Public — image proxies (S3 is private; players need these unauthenticated)
Route::middleware(['web'])
    ->prefix('quiz')
    ->name('quiz.')
    ->group(function () {
        Route::get('/{quiz}/cover', [QuizController::class, 'showCoverImage'])->name('cover')->where('quiz', '[0-9]+');
        Route::get('/questions/{question}/image', [QuizController::class, 'showQuestionImage'])->name('questions.image');
        Route::get('/questions/{question}/explanation-image', [QuizController::class, 'showExplanationImage'])->name('questions.explanation-image');
    });

// ── Public — join & play (no auth, PIN-based, mirrors ams evaluate/verify links)
Route::middleware(['web'])
    ->prefix('quiz')
    ->name('quiz.')
    ->group(function () {
        Route::get('/join', [QuizPlayController::class, 'joinForm'])->name('join');
        Route::get('/join/{pin}', [QuizPlayController::class, 'joinForm'])->name('join.pin')->where('pin', '[0-9]{6}');
        Route::post('/lookup-pin', [QuizPlayController::class, 'lookupPin'])->name('lookup-pin');
        Route::post('/players/join', [QuizPlayController::class, 'join'])->name('players.join');
        Route::get('/play/{playerToken}', [QuizPlayController::class, 'play'])->name('play');
        Route::get('/play/{playerToken}/state', [QuizPlayController::class, 'state'])->name('play.state');
        Route::get('/play/{playerToken}/result', [QuizPlayController::class, 'result'])->name('play.result');
        Route::post('/play/{playerToken}/answer', [QuizPlayController::class, 'submitAnswer'])->name('play.answer');
    });

// ── Management (auth) — index/create/store first, /{quiz} wildcards last
Route::middleware(['web', 'auth', 'verified', 'permission:quiz.manage|quiz.view_all'])
    ->prefix('quiz')
    ->name('quiz.')
    ->group(function () {
        Route::get('/', [QuizController::class, 'index'])->name('index');
        Route::get('/create', [QuizController::class, 'create'])->name('create');
        Route::post('/', [QuizController::class, 'store'])->name('store');

        Route::get('/{quiz}/edit', [QuizController::class, 'edit'])->name('edit')->where('quiz', '[0-9]+');
        Route::post('/{quiz}', [QuizController::class, 'update'])->name('update')->where('quiz', '[0-9]+');
        Route::delete('/{quiz}', [QuizController::class, 'destroy'])->name('destroy')->where('quiz', '[0-9]+');
        Route::post('/{quiz}/duplicate', [QuizController::class, 'duplicate'])->name('duplicate')->where('quiz', '[0-9]+');

        Route::post('/{quiz}/questions', [QuizController::class, 'storeQuestion'])->name('questions.store')->where('quiz', '[0-9]+');
        Route::post('/{quiz}/questions/reorder', [QuizController::class, 'reorderQuestions'])->name('questions.reorder')->where('quiz', '[0-9]+');
        Route::post('/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion'])->name('questions.update')->where(['quiz' => '[0-9]+', 'question' => '[0-9]+']);
        Route::delete('/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion'])->name('questions.destroy')->where(['quiz' => '[0-9]+', 'question' => '[0-9]+']);

        Route::post('/{quiz}/sessions', [QuizSessionController::class, 'store'])->name('sessions.store')->where('quiz', '[0-9]+');
    });

Route::middleware(['web', 'auth', 'verified', 'permission:quiz.manage|quiz.view_all'])
    ->prefix('quiz/sessions')
    ->name('quiz.sessions.')
    ->group(function () {
        Route::get('/{session}', [QuizSessionController::class, 'show'])->name('show');
        Route::get('/{session}/report', [QuizSessionController::class, 'report'])->name('report');
        Route::delete('/{session}/players/{player}', [QuizSessionController::class, 'kickPlayer'])->name('players.kick');
        Route::post('/{session}/start', [QuizSessionController::class, 'start'])->name('start');
        Route::post('/{session}/end-question', [QuizSessionController::class, 'endQuestion'])->name('end-question');
        Route::post('/{session}/show-explanation', [QuizSessionController::class, 'showExplanation'])->name('show-explanation');
        Route::post('/{session}/leaderboard', [QuizSessionController::class, 'leaderboard'])->name('leaderboard');
        Route::post('/{session}/next', [QuizSessionController::class, 'next'])->name('next');
        Route::post('/{session}/end', [QuizSessionController::class, 'end'])->name('end');
    });
