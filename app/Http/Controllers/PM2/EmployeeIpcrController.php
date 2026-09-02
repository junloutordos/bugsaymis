<?php

namespace App\Http\Controllers\PM2;

use App\Http\Controllers\Controller;
use App\Models\PM2\EmployeeIpcrPlanV2;
use App\Models\PM2\EmployeeIpcrV2;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\WorkDistributionPlan;
use App\Services\PerformanceManagementV2\CoreFunctionGeneratorV2;
use App\Services\PerformanceManagementV2\IpcrWorkflowServiceV2;
use App\Services\PerformanceManagementV2\StrategicFunctionInheritorV2;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeIpcrController extends Controller
{
    public function __construct(private IpcrWorkflowServiceV2 $workflow)
    {
    }

    public function index()
    {
        $user = auth()->user();

        return Inertia::render('PerformanceManagementV2/EmployeeIpcr/Index', [
            'ipcrs'         => EmployeeIpcrV2::with('ratingPeriod')->where('user_id', $user->id)->latest()->get(),
            'ratingPeriods' => IpcrRatingPeriodV2::open()->get(['id', 'label', 'year', 'semester']),
        ]);
    }

    public function store(Request $request, StrategicFunctionInheritorV2 $strategic, CoreFunctionGeneratorV2 $core)
    {
        $data = $request->validate([
            'rating_period_id' => 'required|exists:ipcr_rating_periods_v2,id',
            'title'            => 'required|string|max:255',
        ]);

        $ipcr = EmployeeIpcrV2::create([
            'user_id'          => auth()->id(),
            'rating_period_id' => $data['rating_period_id'],
            'title'            => $data['title'],
            'status'           => IpcrWorkflowServiceV2::STATUS_NEW_TARGET,
        ]);

        $strategic->inherit($ipcr);
        $core->generate($ipcr);

        return redirect()->route('pm2.employee-ipcr.show', $ipcr->id)->with('success', 'PM V2 IPCR created.');
    }

    public function show(EmployeeIpcrV2 $ipcr)
    {
        $this->workflow->assertOwner(auth()->user(), $ipcr);

        return Inertia::render('PerformanceManagementV2/EmployeeIpcr/Show', [
            'ipcr'          => $ipcr->load(['ratingPeriod', 'rows.plan.performanceIndicator.agencyOutcome', 'rows.templateItem']),
            'weightTargets' => $this->workflow->weightTargets($ipcr->user?->division_id),
            'weightSums'    => $this->workflow->weightSums($ipcr),
            'isMutable'     => $ipcr->isMutable(),
        ]);
    }

    public function updateRowWeight(Request $request, EmployeeIpcrV2 $ipcr, EmployeeIpcrPlanV2 $row)
    {
        $this->workflow->assertOwner(auth()->user(), $ipcr);
        $this->workflow->assertMutable($ipcr);
        abort_unless($row->ipcr_id === $ipcr->id, 404);
        abort_if($ipcr->status !== IpcrWorkflowServiceV2::STATUS_NEW_TARGET, 403, 'Weights can only be edited while the IPCR is a New Target.');

        $data = $request->validate(['weight_percent' => 'required|numeric|min:0|max:100']);

        $row->update(['weight_percent' => $data['weight_percent']]);
        if ($row->plan_id) {
            WorkDistributionPlan::whereKey($row->plan_id)->update(['weight_percent' => $data['weight_percent']]);
        }

        return back()->with('success', 'Weight saved.');
    }

    public function selfRate(Request $request, EmployeeIpcrV2 $ipcr, EmployeeIpcrPlanV2 $row)
    {
        $this->workflow->assertOwner(auth()->user(), $ipcr);
        $this->workflow->assertMutable($ipcr);
        abort_unless($row->ipcr_id === $ipcr->id, 404);
        abort_if($ipcr->status !== IpcrWorkflowServiceV2::STATUS_TARGETS_APPROVED, 403, 'Self-rating is only allowed once targets are approved.');

        $data = $request->validate([
            'accomplishment'  => 'nullable|string|max:1000',
            'mov_link'        => 'nullable|string|max:500',
            'self_quality'    => 'nullable|integer|min:1|max:5',
            'self_efficiency' => 'nullable|integer|min:1|max:5',
            'self_timeliness' => 'nullable|integer|min:1|max:5',
        ]);

        $ratings = collect([$data['self_quality'] ?? null, $data['self_efficiency'] ?? null, $data['self_timeliness'] ?? null])->filter();

        $row->update(array_merge($data, [
            'self_average' => $ratings->isNotEmpty() ? round($ratings->avg(), 2) : null,
        ]));

        return back()->with('success', 'Self-rating saved.');
    }

    public function submitForRating(EmployeeIpcrV2 $ipcr)
    {
        $this->workflow->assertOwner(auth()->user(), $ipcr);

        $this->workflow->transition($ipcr, IpcrWorkflowServiceV2::STATUS_FOR_RATING, [
            'submitted_for_rating_at' => now(),
        ], 'pm_v2_ipcr_submitted_for_rating');

        return back()->with('success', 'Submitted for rating.');
    }
}
