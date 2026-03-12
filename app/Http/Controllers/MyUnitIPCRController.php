<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\EmployeeIPCR;
use App\Models\EmployeeIPCRPlan;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MyUnitIPCRController extends Controller
{
    private const VIEWABLE_STATUSES = [
        'Submitted for Rating',
        'Rated & For PMT Review',
        'Submitted to PMT',
        'PMT Returned for Revision',
        'Approved by PMT',
    ];

    /**
     * List all employee IPCRs for the offices this user heads.
     */
    public function index()
    {
        $user = auth()->user();

        $offices = Office::where('unit_head', $user->id)->get();

        if ($offices->isEmpty()) {
            abort(403, 'You are not assigned as a Unit Head of any office.');
        }

        $officeIds = $offices->pluck('id');

        $avgSubquery = DB::table('employee_ipcrs_plan')
            ->selectRaw('ROUND(AVG(sup_average), 2)')
            ->whereColumn('ipcr_id', 'employee_ipcrs.id')
            ->whereNotNull('sup_average');

        $ipcrs = EmployeeIPCR::with(['user.office'])
            ->addSelect(['overall_average' => $avgSubquery])
            ->whereHas('user', fn($q) => $q->whereIn('office_id', $officeIds))
            ->whereIn('status', self::VIEWABLE_STATUSES)
            ->orderBy('created_at', 'desc')
            ->get();

        $ratingPeriods = $ipcrs->pluck('rating_period')->filter()->unique()->sort()->values();

        return Inertia::render('PerformanceManagement/MyUnit/Index', [
            'offices'       => $offices,
            'ipcrs'         => $ipcrs,
            'unitHead'      => $user->only('id', 'name', 'position'),
            'ratingPeriods' => $ratingPeriods,
        ]);
    }

    /**
     * Show a single employee IPCR with plans and accomplishments.
     */
    public function show($id)
    {
        $user      = auth()->user();
        $officeIds = Office::where('unit_head', $user->id)->pluck('id');

        if ($officeIds->isEmpty()) {
            abort(403, 'You are not assigned as a Unit Head of any office.');
        }

        $ipcr = EmployeeIPCR::with([
            'user.office',
            'plans.performance_indicator.agencyOutcome',
            'plans.offices',
            'plans.committees',
            'plans.specialAssignments',
        ])->findOrFail($id);

        // Guard: employee must belong to one of the unit head's offices
        if (!$officeIds->contains($ipcr->user->office_id)) {
            abort(403, 'This employee is not in your unit.');
        }

        // Bulk-load accomplishments (2 queries, avoids N+1)
        $ipcrPlanIds = EmployeeIPCRPlan::where('ipcr_id', $ipcr->id)
            ->pluck('id', 'plan_id');

        $accomplishmentsByPivot = Accomplishment::with('photos')
            ->whereIn('ipcr_plan_id', $ipcrPlanIds->values())
            ->orderBy('accomplishment_date', 'desc')
            ->get()
            ->groupBy('ipcr_plan_id');

        $plans = $ipcr->plans->map(function ($plan) use ($ipcrPlanIds, $accomplishmentsByPivot) {
            $pivotId                   = $ipcrPlanIds[$plan->id] ?? null;
            $accs                      = $pivotId ? ($accomplishmentsByPivot[$pivotId] ?? collect()) : collect();
            $plan->ipcr_plan_id        = $pivotId;
            $plan->accomplishments     = $accs->values();
            $plan->accomplishments_count = $accs->count();
            return $plan;
        });

        return Inertia::render('PerformanceManagement/MyUnit/Show', [
            'ipcr'     => $ipcr,
            'plans'    => $plans,
            'employee' => $ipcr->user,
            'unitHead' => $user,
        ]);
    }
}
