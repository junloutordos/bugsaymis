<?php

namespace App\Http\Controllers;

use App\Models\Accomplishment;
use App\Models\EmployeeIPCR;
use App\Models\EmployeeIPCRPlan;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminIPCRController extends Controller
{
    /**
     * Read-only, unscoped view across every stage of the IPCR workflow —
     * Division Chief, HR, PMT, etc. Administrator-only (see routes/web.php).
     */
    public function index()
    {
        $avgSubquery = DB::table('employee_ipcrs_plan')
            ->selectRaw('ROUND(AVG(sup_average), 2)')
            ->whereColumn('ipcr_id', 'employee_ipcrs.id')
            ->whereNotNull('sup_average');

        $ipcrs = EmployeeIPCR::with(['user.division', 'period'])
            ->addSelect(['overall_average' => $avgSubquery])
            ->orderBy('updated_at', 'desc')
            ->get();

        $statuses = collect((new \ReflectionClass(IPCRWorkflowService::class))->getConstants())
            ->filter(fn ($value, $key) => str_starts_with($key, 'STATUS_'))
            ->values();

        $ratingPeriods = $ipcrs
            ->map(fn ($ipcr) => [
                'id'    => $ipcr->rating_period_id,
                'label' => $ipcr->period?->label ?? $ipcr->rating_period,
            ])
            ->filter(fn ($p) => $p['label'])
            ->unique('label')
            ->sortBy('label')
            ->values();

        return Inertia::render('PerformanceManagement/AdminIPCRIndex', [
            'ipcrs'         => $ipcrs,
            'statuses'      => $statuses,
            'statusCounts'  => $ipcrs->countBy('status'),
            'ratingPeriods' => $ratingPeriods,
        ]);
    }

    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division.divisionchief',
            'period',
            'plans.performance_indicator.agencyOutcome.parent',
            'plans.offices',
            'plans.committees',
            'plans.specialAssignments',
            'coachingSessions',
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

        return Inertia::render('PerformanceManagement/AdminIPCRShow', [
            'ipcr'       => $ipcr,
            'plans'      => $plans,
            'employee'   => $ipcr->user,
            'supervisor' => $supervisor,
            'ocdUser'    => $ocdUser ? $ocdUser->only('name', 'position') : null,
        ]);
    }
}
