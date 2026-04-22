<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\SaveEvaluationScoresRequest;
use App\Http\Requests\Recruitment\ScheduleInterviewRequest;
use App\Models\Application;
use App\Models\Interview;
use App\Services\Recruitment\EvaluationService;
use Illuminate\Http\Request;

class EvaluationController extends Controller
{
    public function __construct(private EvaluationService $service) {}

    /**
     * Save evaluation scores for an application.
     */
    public function saveScores(SaveEvaluationScoresRequest $request, Application $application)
    {
        $this->authorize('recruitment.evaluate');

        try {
            $this->service->saveScores($application, $request->scores);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Scores saved successfully.');
    }

    /**
     * Schedule an interview for an application.
     */
    public function scheduleInterview(ScheduleInterviewRequest $request, Application $application)
    {
        $this->authorize('recruitment.evaluate');

        if (! $application->recruitmentType->has_interview) {
            return back()->withErrors(['error' => 'This recruitment type does not include interviews.']);
        }

        $interview = $this->service->scheduleInterview($application, $request->validated());

        return back()->with('success', "Interview scheduled for {$interview->interview_date->format('M d, Y H:i')}.");
    }

    /**
     * Record the result of a completed interview.
     */
    public function recordInterviewResult(Request $request, Application $application, Interview $interview)
    {
        $this->authorize('recruitment.evaluate');

        $validated = $request->validate([
            'rating'  => ['required', 'numeric', 'min:0', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->recordInterviewResult($interview, $validated['rating'], $validated['remarks'] ?? '');

        return back()->with('success', 'Interview result recorded.');
    }

    /**
     * Compute ranking for a single application.
     */
    public function computeRanking(Application $application)
    {
        $this->authorize('recruitment.rank');

        try {
            $summary = $this->service->computeRanking($application);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Total score: {$summary->total_score}");
    }

    /**
     * Recompute and assign ranks for all applications in a vacancy.
     */
    public function reRankVacancy(int $jobVacancyId)
    {
        $this->authorize('recruitment.rank');

        $this->service->reRankVacancy($jobVacancyId);

        return back()->with('success', 'Ranking updated for all applicants in this vacancy.');
    }

    /**
     * Mark/unmark an application as recommended.
     */
    public function recommend(Request $request, Application $application)
    {
        $this->authorize('recruitment.rank');

        $validated = $request->validate([
            'is_recommended'     => ['required', 'boolean'],
            'deliberation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->service->recommend(
                $application,
                $validated['is_recommended'],
                $validated['deliberation_notes'] ?? null
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Recommendation status updated.');
    }
}
