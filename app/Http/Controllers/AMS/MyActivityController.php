<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityEvaluation;
use App\Models\AMS\ActivityParticipant;
use App\Models\AMS\ActivityTwsEvaluation;
use App\Services\AMS\ActivityEvaluationEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MyActivityController extends Controller
{
    public function __construct(private ActivityEvaluationEligibilityService $evaluationEligibility) {}

    // ── Index — list of activities the current user participated in ────────────

    public function index(Request $request)
    {
        $userId = Auth::id();

        $participants = ActivityParticipant::with('activity')
            ->where('participant_id', $userId)
            ->where('participant_type', 'employee')
            ->get();

        $activityIds = $participants->pluck('activity_id');
        $inHouseEvaluatedIds = ActivityEvaluation::where('participant_type', 'employee')
            ->where('participant_id', $userId)
            ->whereIn('activity_id', $activityIds)
            ->pluck('activity_id')
            ->flip()
            ->all();
        $twsEvaluatedIds = ActivityTwsEvaluation::where('participant_type', 'employee')
            ->where('participant_id', $userId)
            ->whereIn('activity_id', $activityIds)
            ->pluck('activity_id')
            ->flip()
            ->all();

        $activities = $participants
            ->filter(fn($p) => $p->activity !== null)
            ->sortByDesc(fn($p) => $p->activity->start_date)
            ->values()
            ->map(function ($p) use ($inHouseEvaluatedIds, $twsEvaluatedIds) {
                $activity  = $p->activity;
                $evaluated = $activity->isTrainingWorkshopSeminar()
                    ? isset($twsEvaluatedIds[$activity->id])
                    : isset($inHouseEvaluatedIds[$activity->id]);
                $hash      = md5($p->participant_id . '-' . $activity->id);

                return [
                    'participant_row_id' => $p->id,
                    'id'                 => $activity->id,
                    'title'              => $activity->title,
                    'start_date'         => $activity->start_date?->toDateString(),
                    'end_date'           => $activity->end_date?->toDateString(),
                    'venue'              => $activity->venue,
                    'total_hours'        => $activity->total_hours,
                    'attended'           => $p->attended,          // 'yes' | 'no'
                    'evaluated'          => $evaluated,
                    'has_certificate'    => $evaluated && $p->attended === 'yes' && (bool) $p->certificate_path,
                    'evaluate_url'       => route('ams.activities.evaluate.show', [$activity->id, $hash]),
                ];
            });

        return Inertia::render('AMS/MyActivities/Index', [
            'activities' => $activities,
        ]);
    }

    // ── Show — detail view for one activity ───────────────────────────────────

    public function show(Request $request, Activity $activity)
    {
        $userId = Auth::id();

        $participant = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_id', $userId)
            ->where('participant_type', 'employee')
            ->firstOrFail();

        $evaluated = $this->evaluationEligibility->hasEvaluated($activity, 'employee', $userId);

        $hash = md5($userId . '-' . $activity->id);

        return Inertia::render('AMS/MyActivities/Show', [
            'activity' => [
                'id'                   => $activity->id,
                'title'                => $activity->title,
                'start_date'           => $activity->start_date?->toDateString(),
                'end_date'             => $activity->end_date?->toDateString(),
                'start_time'           => $activity->start_time,
                'end_time'             => $activity->end_time,
                'venue'                => $activity->venue,
                'resource_person'      => $activity->resource_person,
                'total_hours'          => $activity->total_hours,
                'banner'               => $activity->banner,
                'special_order'        => $activity->special_order,
                'activity_report'      => $activity->activity_report,
                'official_documentation' => $activity->official_documentation,
            ],
            'participant' => [
                'id'              => $participant->id,
                'attended'        => $participant->attended,
                'hours_attended'  => $participant->hours_attended,
                'has_certificate' => $evaluated && $participant->attended === 'yes' && (bool) $participant->certificate_path,
            ],
            'evaluated'    => $evaluated,
            'evaluate_url' => route('ams.activities.evaluate.show', [$activity->id, $hash]),
            'cert_url'     => $evaluated && $participant->attended === 'yes' && $participant->certificate_path
                ? route('ams.my-activities.certificate', $activity->id)
                : null,
        ]);
    }

    // ── Self-service certificate download ─────────────────────────────────────

    public function downloadCertificate(Activity $activity)
    {
        $userId = Auth::id();

        $participant = ActivityParticipant::where('activity_id', $activity->id)
            ->where('participant_id', $userId)
            ->where('participant_type', 'employee')
            ->firstOrFail();

        // Must have attended
        if ($participant->attended !== 'yes') {
            abort(403, 'Certificate is only available for participants who attended.');
        }

        // Must have evaluated
        $evaluated = $this->evaluationEligibility->hasEvaluated($activity, 'employee', $userId);

        if (!$evaluated) {
            abort(403, 'You must complete the activity evaluation before downloading your certificate.');
        }

        // Must have a generated certificate
        if (!$participant->certificate_path) {
            abort(404, 'Certificate has not been generated yet. Please contact the activity organizer.');
        }

        $content  = Storage::disk('public')->get($participant->certificate_path);
        $filename = 'Certificate_' . str_replace(' ', '_', $activity->title) . '.pdf';

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
