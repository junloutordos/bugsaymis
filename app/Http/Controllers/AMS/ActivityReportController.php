<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityCoProponent;
use App\Services\AMS\ActivityReportService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ActivityReportController extends Controller
{
    public function __construct(private ActivityReportService $reportService) {}

    public function show(Activity $activity)
    {
        $this->authorizeView($activity);
        $activity->load('creator');

        return Inertia::render('AMS/Report', [
            'activity' => $this->activityHeader($activity),
            'report'   => $this->reportService->buildReport($activity),
        ]);
    }

    public function print(Activity $activity)
    {
        $this->authorizeView($activity);
        $activity->load('creator');

        return Inertia::render('AMS/ReportPrint', [
            'activity' => $this->activityHeader($activity),
            'report'   => $this->reportService->buildReport($activity),
        ]);
    }

    private function activityHeader(Activity $activity): array
    {
        return [
            'id'         => $activity->id,
            'title'      => $activity->title,
            'venue'      => $activity->venue,
            'start_date' => $activity->start_date?->toDateString(),
            'end_date'   => $activity->end_date?->toDateString(),
            'proponent'  => $activity->creator?->name,
        ];
    }

    private function authorizeView(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->hasAnyPermission(['activities.view_all', 'activities.monitor', 'activities.evaluation_committee'])) {
            return;
        }

        $isOwner = $activity->user_id === $user->id;
        $isCo    = ActivityCoProponent::where('activity_id', $activity->id)->where('employee_id', $user->id)->exists();
        abort_unless($isOwner || $isCo, 403);
    }
}
