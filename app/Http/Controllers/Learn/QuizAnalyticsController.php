<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Services\Learn\QuizAnalyticsService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class QuizAnalyticsController extends Controller
{
    public function __construct(private QuizAnalyticsService $analyticsService)
    {
    }

    /** GET /learn/quizzes/{quiz}/analytics */
    public function show(Quiz $quiz): Response
    {
        $user = Auth::user();
        abort_unless($quiz->canEdit($user), 403);

        return Inertia::render('Learn/QuizAnalytics', [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title],
            'analysis' => $this->analyticsService->itemAnalysis($quiz),
        ]);
    }

    /** GET /learn/{course}/quiz-trend */
    public function courseTrend(Course $course): Response
    {
        $user = Auth::user();
        abort_unless($course->canView($user), 403);

        $course->load(['subject', 'modules.items.itemable']);

        return Inertia::render('Learn/QuizCourseTrend', [
            'course' => ['id' => $course->id, 'subject_name' => $course->subject->name],
            'trend' => $this->analyticsService->courseTrend($course),
        ]);
    }
}
