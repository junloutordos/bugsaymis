<?php

namespace App\Http\Controllers;

use App\Mail\IPCRSubmittedToPMTMail;
use App\Models\Accomplishment;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\EmployeeIPCRPlan;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class HRIPCRController extends Controller
{
    public function __construct(private IPCRWorkflowService $workflow)
    {
    }

    public function index()
    {
        $avgSubquery = DB::table('employee_ipcrs_plan')
            ->selectRaw('ROUND(AVG(sup_average), 2)')
            ->whereColumn('ipcr_id', 'employee_ipcrs.id')
            ->whereNotNull('sup_average');

        $ipcrs = EmployeeIPCR::with(['user.division', 'period'])
            ->addSelect(['overall_average' => $avgSubquery])
            ->whereIn('status', ['Submitted to HR'])
            ->orderBy('submitted_to_hr_at', 'desc')
            ->get();

        $ratingPeriods = $ipcrs
            ->map(fn ($ipcr) => [
                'id'    => $ipcr->rating_period_id,
                'label' => $ipcr->period?->label ?? $ipcr->rating_period,
            ])
            ->filter(fn ($p) => $p['label'])
            ->unique('label')
            ->sortBy('label')
            ->values();

        return Inertia::render('PerformanceManagement/HRIPCRIndex', [
            'ipcrs'         => $ipcrs,
            'ratingPeriods' => $ratingPeriods,
        ]);
    }

    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division.divisionchief',
            'period',
            'plans.performance_indicator.agencyOutcome',
            'plans.offices',
            'plans.committees',
            'plans.specialAssignments',
        ])->findOrFail($id);

        $ipcrPlanIds = EmployeeIPCRPlan::where('ipcr_id', $ipcr->id)
            ->pluck('id', 'plan_id');

        $accomplishmentsByPivot = Accomplishment::with('photos')
            ->whereIn('ipcr_plan_id', $ipcrPlanIds->values())
            ->orderBy('accomplishment_date', 'desc')
            ->get()
            ->groupBy('ipcr_plan_id');

        $plans = $ipcr->plans->map(function ($plan) use ($ipcrPlanIds, $accomplishmentsByPivot) {
            $pivotId = $ipcrPlanIds[$plan->id] ?? null;
            $accs    = $pivotId ? ($accomplishmentsByPivot[$pivotId] ?? collect()) : collect();
            $plan->ipcr_plan_id          = $pivotId;
            $plan->accomplishments        = $accs->values();
            $plan->accomplishments_count  = $accs->count();
            return $plan;
        });

        $supervisor = $ipcr->user->hasRole('DivisionChief')
            ? User::havingRole('OCD')->first()
            : ($ipcr->user->division->divisionchief ?? null);

        $ocdUser = User::havingRole('OCD')->first();

        return Inertia::render('PerformanceManagement/HRIPCRShow', [
            'ipcr'       => $ipcr,
            'plans'      => $plans,
            'employee'   => $ipcr->user,
            'supervisor' => $supervisor,
            'ocdUser'    => $ocdUser ? $ocdUser->only('name', 'position') : null,
        ]);
    }

    public function submitToPMT(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_SUBMITTED_PMT, [
            'submitted_for_pmtreview_at' => now(),
        ], 'ipcr_submitted_to_pmt');

        User::havingRole('PMT')->each(function ($pmt) use ($employeeIPCR) {
            Mail::to($pmt->email)->send(new IPCRSubmittedToPMTMail($employeeIPCR, $pmt->name));
        });

        return redirect()->back()->with('success', 'IPCR submitted to PMT for review.');
    }

    public function batchSubmitToPMT(Request $request)
    {
        $request->validate([
            'rating_period_id' => 'nullable|exists:ipcr_rating_periods,id',
            'rating_period'    => 'required_without:rating_period_id|nullable|string',
        ]);

        $ipcrs = EmployeeIPCR::where('status', IPCRWorkflowService::STATUS_SUBMITTED_HR)
            ->when($request->rating_period_id,
                fn($q) => $q->where('rating_period_id', $request->rating_period_id),
                fn($q) => $q->where('rating_period', $request->rating_period))
            ->get();

        foreach ($ipcrs as $ipcr) {
            $this->workflow->transition($ipcr, IPCRWorkflowService::STATUS_SUBMITTED_PMT, [
                'submitted_for_pmtreview_at' => now(),
            ], 'ipcr_submitted_to_pmt');
        }

        User::havingRole('PMT')->each(function ($pmt) use ($ipcrs) {
            foreach ($ipcrs as $ipcr) {
                Mail::to($pmt->email)->send(new IPCRSubmittedToPMTMail($ipcr, $pmt->name));
            }
        });

        return redirect()->back()->with('success', $ipcrs->count().' IPCR(s) submitted to PMT for the selected period.');
    }
}
