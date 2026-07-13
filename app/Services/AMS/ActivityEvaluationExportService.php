<?php

namespace App\Services\AMS;

use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityTwsEvaluation;
use Illuminate\Support\Collection;

/**
 * Shapes data for the AMS evaluation Excel exports. Reuses
 * ActivityEvaluationSummaryService for all aggregation math — this class only
 * assembles/queries raw rows and the Monitor-style per-activity roll-up.
 */
class ActivityEvaluationExportService
{
    public function __construct(private ActivityEvaluationSummaryService $summaryService) {}

    public function perActivityData(Activity $activity): array
    {
        $isTws   = $activity->isTrainingWorkshopSeminar();
        $summary = $this->summaryService->build($activity);

        $raw = $isTws
            ? ActivityTwsEvaluation::where('activity_id', $activity->id)->orderByDesc('created_at')->get()
            : ActivityEvaluation::where('activity_id', $activity->id)->orderByDesc('created_at')->get();

        return [
            'is_tws'       => $isTws,
            'summary'      => $summary,
            'field_labels' => $this->flattenFieldLabels($this->summaryService->sectionDefinitions($activity)),
            'raw'          => $raw,
        ];
    }

    public function bulkData(Collection $activities): array
    {
        $activityIds = $activities->pluck('id');

        $participantStats = ActivityParticipant::selectRaw(
            'activity_id, COUNT(*) as total, SUM(CASE WHEN attended = "yes" THEN 1 ELSE 0 END) as attended'
        )->whereIn('activity_id', $activityIds)->groupBy('activity_id')->get()->keyBy('activity_id');

        $inHouseEvalCounts = ActivityEvaluation::selectRaw('activity_id, COUNT(*) as total')
            ->whereIn('activity_id', $activityIds)->groupBy('activity_id')->pluck('total', 'activity_id');

        $twsEvalCounts = ActivityTwsEvaluation::selectRaw('activity_id, COUNT(*) as total')
            ->whereIn('activity_id', $activityIds)->groupBy('activity_id')->pluck('total', 'activity_id');

        $today = now()->toDateString();

        $summaryRows = $activities->map(function (Activity $a) use ($participantStats, $inHouseEvalCounts, $twsEvalCounts, $today) {
            $startDate = $a->start_date?->toDateString();
            $endDate   = $a->end_date?->toDateString();

            $status = match (true) {
                $endDate && $endDate < $today     => 'completed',
                $startDate && $startDate > $today => 'upcoming',
                default                            => 'ongoing',
            };

            $pStats           = $participantStats[$a->id] ?? null;
            $participantCount = $pStats->total ?? 0;
            $attendedCount    = (int) ($pStats->attended ?? 0);
            $evalCount        = $a->isTrainingWorkshopSeminar()
                ? (int) ($twsEvalCounts[$a->id] ?? 0)
                : (int) ($inHouseEvalCounts[$a->id] ?? 0);

            return [
                'title'                      => $a->title,
                'activity_type'              => $a->activity_type,
                'start_date'                 => $startDate,
                'end_date'                   => $endDate,
                'venue'                      => $a->venue,
                'creator'                    => $a->creator?->name,
                'status'                     => $status,
                'participant_count'          => $participantCount,
                'attended_count'             => $attendedCount,
                'evaluation_count'           => $evalCount,
                'evaluation_completion_rate' => $attendedCount > 0 ? round(($evalCount / $attendedCount) * 100, 1) : null,
                'avg_score'                  => $this->averageScoreForActivity($a),
            ];
        })->values()->all();

        $inHouseActivities = $activities->filter(fn (Activity $a) => !$a->isTrainingWorkshopSeminar())->values();
        $twsActivities     = $activities->filter(fn (Activity $a) => $a->isTrainingWorkshopSeminar())->values();

        $inHouseRaw = $inHouseActivities->isEmpty()
            ? collect()
            : ActivityEvaluation::whereIn('activity_id', $inHouseActivities->pluck('id'))
                ->with('activity:id,title')
                ->orderBy('activity_id')->orderByDesc('created_at')->get();

        $twsRaw = $twsActivities->isEmpty()
            ? collect()
            : ActivityTwsEvaluation::whereIn('activity_id', $twsActivities->pluck('id'))
                ->with('activity:id,title')
                ->orderBy('activity_id')->orderByDesc('created_at')->get();

        return [
            'summary'         => $summaryRows,
            'in_house_raw'    => $inHouseRaw,
            'tws_raw'         => $twsRaw,
            'in_house_labels' => $inHouseActivities->isEmpty()
                ? []
                : $this->flattenFieldLabels($this->summaryService->sectionDefinitions($inHouseActivities->first())),
            'tws_labels' => $twsActivities->isEmpty()
                ? []
                : $this->flattenFieldLabels($this->summaryService->sectionDefinitions($twsActivities->first())),
        ];
    }

    private function averageScoreForActivity(Activity $activity): ?float
    {
        if ($activity->isTrainingWorkshopSeminar()) {
            $rows = ActivityTwsEvaluation::where('activity_id', $activity->id)->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $scores = [];
            foreach ($rows as $row) {
                foreach ([...ActivityTwsEvaluation::SECTION_A_FIELDS, ...ActivityTwsEvaluation::SECTION_C_FIELDS] as $field) {
                    $w = ActivityTwsEvaluation::AGREE_WEIGHTS[$row->$field] ?? null;
                    if ($w !== null) {
                        $scores[] = $w;
                    }
                }
                foreach (ActivityTwsEvaluation::SECTION_B_FIELDS as $field) {
                    $w = ActivityTwsEvaluation::SATISFACTION_WEIGHTS[$row->$field] ?? null;
                    if ($w !== null) {
                        $scores[] = $w;
                    }
                }
            }

            return count($scores) ? round(array_sum($scores) / count($scores), 2) : null;
        }

        $rows = ActivityEvaluation::where('activity_id', $activity->id)->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $scores = [];
        foreach ($rows as $row) {
            foreach (ActivityEvaluation::ALL_RATING_FIELDS as $field) {
                $w = ActivityEvaluation::WEIGHTS[$row->$field] ?? null;
                if ($w !== null) {
                    $scores[] = $w;
                }
            }
        }

        return count($scores) ? round(array_sum($scores) / count($scores), 2) : null;
    }

    private function flattenFieldLabels(array $sectionDefs): array
    {
        $labels = [];
        foreach ($sectionDefs as $section) {
            foreach ($section['questions'] as $field => $label) {
                $labels[$field] = $label;
            }
        }

        return $labels;
    }
}
