<?php

namespace App\Services\Recruitment;

use App\Models\Application;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationScore;
use App\Models\Interview;
use App\Models\RankingSummary;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EvaluationService
{
    /**
     * Save (upsert) evaluation scores for a single application.
     * $scores = [ ['criteria_id' => X, 'score' => Y, 'remarks' => '...'], ... ]
     */
    public function saveScores(Application $application, array $scores): Collection
    {
        return DB::transaction(function () use ($application, $scores) {
            $evaluatorId = Auth::id();
            $saved = collect();

            foreach ($scores as $item) {
                $criteria = EvaluationCriteria::findOrFail($item['criteria_id']);

                // Validate criteria belongs to this application's recruitment type
                if ($criteria->recruitment_type_id !== $application->recruitment_type_id) {
                    throw new \InvalidArgumentException(
                        "Criteria [{$criteria->name}] does not belong to this recruitment type."
                    );
                }

                $computedScore = ($item['score'] * $criteria->weight_percentage) / 100;

                $score = EvaluationScore::updateOrCreate(
                    [
                        'application_id' => $application->id,
                        'criteria_id'    => $criteria->id,
                        'evaluator_id'   => $evaluatorId,
                    ],
                    [
                        'score'          => $item['score'],
                        'computed_score' => $computedScore,
                        'remarks'        => $item['remarks'] ?? null,
                    ]
                );

                $saved->push($score);
            }

            AuditLogger::log([
                'action'         => 'edit',
                'auditable_type' => Application::class,
                'auditable_id'   => $application->id,
                'new_values'     => ['evaluation_scores_saved' => $saved->count()],
            ]);

            return $saved;
        });
    }

    /**
     * Schedule or update an interview for an application.
     */
    public function scheduleInterview(Application $application, array $data): Interview
    {
        return DB::transaction(function () use ($application, $data) {
            $interview = Interview::updateOrCreate(
                ['application_id' => $application->id],
                [
                    'interview_date' => $data['interview_date'],
                    'panel_members'  => $data['panel_members'] ?? [],
                    'venue'          => $data['venue'] ?? null,
                    'format'         => $data['format'] ?? 'panel',
                    'status'         => 'scheduled',
                ]
            );

            AuditLogger::logModelEvent($interview, $interview->wasRecentlyCreated ? 'created' : 'updated');

            return $interview;
        });
    }

    /**
     * Record interview result (rating + remarks).
     */
    public function recordInterviewResult(Interview $interview, float $rating, string $remarks = ''): Interview
    {
        return DB::transaction(function () use ($interview, $rating, $remarks) {
            $interview->update([
                'rating'  => $rating,
                'remarks' => $remarks,
                'status'  => 'completed',
            ]);

            AuditLogger::logModelEvent($interview, 'updated');

            return $interview->fresh();
        });
    }

    /**
     * Compute (or recompute) the ranking summary for an application.
     * Sums all computed_scores across all evaluators (averaged if multiple evaluators).
     */
    public function computeRanking(Application $application): RankingSummary
    {
        return DB::transaction(function () use ($application) {
            $scores = EvaluationScore::where('application_id', $application->id)->get();

            if ($scores->isEmpty()) {
                throw new \RuntimeException('No evaluation scores found for this application.');
            }

            // Group by criteria, average across evaluators
            $totalScore = $scores
                ->groupBy('criteria_id')
                ->map(fn ($group) => $group->avg('computed_score'))
                ->sum();

            $summary = RankingSummary::updateOrCreate(
                ['application_id' => $application->id],
                ['total_score' => $totalScore]
            );

            // Update priority_score on application for fast sorting
            $application->update(['priority_score' => $totalScore]);

            AuditLogger::logModelEvent($summary, $summary->wasRecentlyCreated ? 'created' : 'updated');

            return $summary;
        });
    }

    /**
     * Recompute and assign ranks for ALL applications in the same vacancy.
     * Higher total_score = lower (better) rank.
     */
    public function reRankVacancy(int $jobVacancyId): void
    {
        DB::transaction(function () use ($jobVacancyId) {
            $summaries = RankingSummary::whereHas('application', fn ($q) =>
                $q->where('job_vacancy_id', $jobVacancyId)->active()
            )
            ->orderByDesc('total_score')
            ->get();

            foreach ($summaries as $index => $summary) {
                $summary->update(['rank' => $index + 1]);
            }
        });
    }

    /**
     * Mark an application as recommended (top pick from ranking).
     */
    public function recommend(Application $application, bool $recommended = true, ?string $notes = null): RankingSummary
    {
        $summary = $application->rankingSummary
            ?? throw new \RuntimeException('No ranking summary exists for this application.');

        $summary->update([
            'is_recommended'     => $recommended,
            'deliberation_notes' => $notes ?? $summary->deliberation_notes,
        ]);

        AuditLogger::logModelEvent($summary, 'updated');

        return $summary->fresh();
    }
}
