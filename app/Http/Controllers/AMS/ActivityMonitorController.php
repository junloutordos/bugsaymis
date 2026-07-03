<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityTwsEvaluation;
use Inertia\Inertia;

/**
 * Aggregate monitoring dashboard for the evaluation committee/management/
 * administrator — all activities and their evaluation stats in one place.
 * Drill-down reuses the existing ams.activities.show page rather than
 * duplicating the per-activity evaluation detail view.
 */
class ActivityMonitorController extends Controller
{
    public function index()
    {
        $activities = Activity::with('creator')->orderByDesc('start_date')->get();

        $participantStats = ActivityParticipant::selectRaw(
            'activity_id, COUNT(*) as total, SUM(CASE WHEN attended = "yes" THEN 1 ELSE 0 END) as attended'
        )->groupBy('activity_id')->get()->keyBy('activity_id');

        $inHouseEvalCounts = ActivityEvaluation::selectRaw('activity_id, COUNT(*) as total')
            ->groupBy('activity_id')->pluck('total', 'activity_id');

        $twsEvalCounts = ActivityTwsEvaluation::selectRaw('activity_id, COUNT(*) as total')
            ->groupBy('activity_id')->pluck('total', 'activity_id');

        $today = now()->toDateString();

        $rows = $activities->map(function ($a) use ($participantStats, $inHouseEvalCounts, $twsEvalCounts, $today) {
            $startDate = $a->start_date?->toDateString();
            $endDate   = $a->end_date?->toDateString();

            $status = match (true) {
                $endDate && $endDate < $today     => 'completed',
                $startDate && $startDate > $today => 'upcoming',
                default                            => 'ongoing',
            };

            $pStats          = $participantStats[$a->id] ?? null;
            $participantCount = $pStats->total ?? 0;
            $attendedCount    = (int) ($pStats->attended ?? 0);
            $evalCount        = $a->isTrainingWorkshopSeminar()
                ? (int) ($twsEvalCounts[$a->id] ?? 0)
                : (int) ($inHouseEvalCounts[$a->id] ?? 0);

            return [
                'id'                         => $a->id,
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
                'show_url'                   => route('ams.activities.show', $a->id),
            ];
        })->values();

        $byType   = $activities->countBy('activity_type');
        $byStatus = $rows->countBy('status');

        $trend = Activity::selectRaw("DATE_FORMAT(start_date, '%Y-%m') as ym, COUNT(*) as total")
            ->where('start_date', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn($r) => ['month' => $r->ym, 'count' => (int) $r->total])
            ->values()->all();

        return Inertia::render('AMS/Monitor/Index', [
            'stats' => [
                'total_activities' => $activities->count(),
                'by_type'          => [
                    'in_house'                  => $byType['in_house'] ?? 0,
                    'training_workshop_seminar' => $byType['training_workshop_seminar'] ?? 0,
                ],
                'by_status' => [
                    'upcoming'  => $byStatus['upcoming'] ?? 0,
                    'ongoing'   => $byStatus['ongoing'] ?? 0,
                    'completed' => $byStatus['completed'] ?? 0,
                ],
                'total_participants'             => $rows->sum('participant_count'),
                'total_evaluations'               => $rows->sum('evaluation_count'),
                'avg_evaluation_completion_rate'  => $this->avgRounded($rows->pluck('evaluation_completion_rate')->filter()),
                'avg_in_house_score'              => $this->averageInHouseScore(),
                'avg_tws_score'                    => $this->averageTwsScore(),
            ],
            'trend'      => $trend,
            'activities' => $rows->all(),
        ]);
    }

    private function avgRounded($values): ?float
    {
        return $values->isEmpty() ? null : round($values->avg(), 1);
    }

    private function averageInHouseScore(): ?float
    {
        $rows = ActivityEvaluation::all();
        if ($rows->isEmpty()) return null;

        $scores = [];
        foreach ($rows as $row) {
            foreach (ActivityEvaluation::ALL_RATING_FIELDS as $field) {
                $w = ActivityEvaluation::WEIGHTS[$row->$field] ?? null;
                if ($w !== null) $scores[] = $w;
            }
        }

        return count($scores) ? round(array_sum($scores) / count($scores), 2) : null;
    }

    private function averageTwsScore(): ?float
    {
        $rows = ActivityTwsEvaluation::all();
        if ($rows->isEmpty()) return null;

        $scores = [];
        foreach ($rows as $row) {
            foreach ([...ActivityTwsEvaluation::SECTION_A_FIELDS, ...ActivityTwsEvaluation::SECTION_C_FIELDS] as $field) {
                $w = ActivityTwsEvaluation::AGREE_WEIGHTS[$row->$field] ?? null;
                if ($w !== null) $scores[] = $w;
            }
            foreach (ActivityTwsEvaluation::SECTION_B_FIELDS as $field) {
                $w = ActivityTwsEvaluation::SATISFACTION_WEIGHTS[$row->$field] ?? null;
                if ($w !== null) $scores[] = $w;
            }
        }

        return count($scores) ? round(array_sum($scores) / count($scores), 2) : null;
    }
}
