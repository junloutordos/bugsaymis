<?php

namespace App\Http\Controllers\PM2;

use App\Http\Controllers\Controller;
use App\Models\PM2\EmployeeIpcrPlanV2;
use App\Models\PM2\EmployeeIpcrV2;
use App\Services\PerformanceManagementV2\IpcrWorkflowServiceV2;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupervisorIpcrController extends Controller
{
    public function __construct(private IpcrWorkflowServiceV2 $workflow)
    {
    }

    public function index()
    {
        $user = auth()->user();

        $ipcrs = EmployeeIpcrV2::with(['user', 'ratingPeriod'])
            ->get()
            ->filter(fn ($ipcr) => $this->workflow->canManage($user, $ipcr))
            ->values();

        return Inertia::render('PerformanceManagementV2/SupervisorIpcr/Index', ['ipcrs' => $ipcrs]);
    }

    public function show(EmployeeIpcrV2 $ipcr)
    {
        $this->workflow->assertCanManage(auth()->user(), $ipcr);

        return Inertia::render('PerformanceManagementV2/SupervisorIpcr/Show', [
            'ipcr'          => $ipcr->load(['user', 'ratingPeriod', 'rows.plan.performanceIndicator.agencyOutcome', 'rows.templateItem']),
            'weightTargets' => $this->workflow->weightTargets($ipcr->user?->division_id),
            'weightSums'    => $this->workflow->weightSums($ipcr),
        ]);
    }

    public function approveTargets(EmployeeIpcrV2 $ipcr)
    {
        $this->workflow->assertCanManage(auth()->user(), $ipcr);
        $this->workflow->assertWeightsValid($ipcr);

        $this->workflow->transition($ipcr, IpcrWorkflowServiceV2::STATUS_TARGETS_APPROVED, [
            'target_approved_at' => now(),
        ], 'pm_v2_ipcr_targets_approved');

        return back()->with('success', 'Targets approved.');
    }

    public function rateRow(Request $request, EmployeeIpcrV2 $ipcr, EmployeeIpcrPlanV2 $row)
    {
        $this->workflow->assertCanManage(auth()->user(), $ipcr);
        $this->workflow->assertMutable($ipcr);
        abort_unless($row->ipcr_id === $ipcr->id, 404);
        abort_if($ipcr->status !== IpcrWorkflowServiceV2::STATUS_FOR_RATING, 403, 'Rating is only allowed while the IPCR is submitted for rating.');

        $data = $request->validate([
            'sup_quality'    => 'nullable|integer|min:1|max:5',
            'sup_efficiency' => 'nullable|integer|min:1|max:5',
            'sup_timeliness' => 'nullable|integer|min:1|max:5',
        ]);

        $ratings = collect([$data['sup_quality'] ?? null, $data['sup_efficiency'] ?? null, $data['sup_timeliness'] ?? null])->filter();

        $row->update(array_merge($data, [
            'sup_average' => $ratings->isNotEmpty() ? round($ratings->avg(), 2) : null,
        ]));

        return back()->with('success', 'Row rated.');
    }

    public function markRated(EmployeeIpcrV2 $ipcr)
    {
        $this->workflow->assertCanManage(auth()->user(), $ipcr);

        $this->workflow->finalize($ipcr);

        return back()->with('success', 'IPCR rated.');
    }
}
