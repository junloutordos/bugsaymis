<?php

namespace App\Http\Controllers;

use App\Mail\IPCRSubmittedToPMTMail;
use App\Models\Accomplishment;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\EmployeeIPCRPlan;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class HRIPCRController extends Controller
{
    public function index()
    {
        $avgSubquery = DB::table('employee_ipcrs_plan')
            ->selectRaw('ROUND(AVG(sup_average), 2)')
            ->whereColumn('ipcr_id', 'employee_ipcrs.id')
            ->whereNotNull('sup_average');

        $ipcrs = EmployeeIPCR::with(['user.division'])
            ->addSelect(['overall_average' => $avgSubquery])
            ->whereIn('status', ['Submitted to HR'])
            ->orderBy('submitted_to_hr_at', 'desc')
            ->get();

        $ratingPeriods = $ipcrs->pluck('rating_period')->filter()->unique()->sort()->values();

        return Inertia::render('PerformanceManagement/HRIPCRIndex', [
            'ipcrs'         => $ipcrs,
            'ratingPeriods' => $ratingPeriods,
        ]);
    }

    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division.divisionchief',
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

        return Inertia::render('PerformanceManagement/HRIPCRShow', [
            'ipcr'       => $ipcr,
            'plans'      => $plans,
            'employee'   => $ipcr->user,
            'supervisor' => $supervisor,
        ]);
    }

    public function submitToPMT(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status'                     => 'Submitted to PMT',
            'submitted_for_pmtreview_at' => now(),
        ]);

        User::havingRole('PMT')->each(function ($pmt) use ($employeeIPCR) {
            Mail::to($pmt->email)->send(new IPCRSubmittedToPMTMail($employeeIPCR, $pmt->name));
        });

        AuditLogger::log([
            'action'         => 'ipcr_submitted_to_pmt',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['status' => 'Submitted to PMT'],
        ]);

        return redirect()->back()->with('success', 'IPCR submitted to PMT for review.');
    }

    public function batchSubmitToPMT(Request $request)
    {
        $request->validate(['rating_period' => 'required|string']);

        $ipcrs = EmployeeIPCR::where('status', 'Submitted to HR')
            ->where('rating_period', $request->rating_period)
            ->get();

        foreach ($ipcrs as $ipcr) {
            $ipcr->update([
                'status'                     => 'Submitted to PMT',
                'submitted_for_pmtreview_at' => now(),
            ]);

            AuditLogger::log([
                'action'         => 'ipcr_submitted_to_pmt',
                'auditable_type' => EmployeeIPCR::class,
                'auditable_id'   => $ipcr->id,
                'new_values'     => ['status' => 'Submitted to PMT'],
            ]);
        }

        User::havingRole('PMT')->each(function ($pmt) use ($ipcrs) {
            foreach ($ipcrs as $ipcr) {
                Mail::to($pmt->email)->send(new IPCRSubmittedToPMTMail($ipcr, $pmt->name));
            }
        });

        return redirect()->back()->with('success', $ipcrs->count().' IPCR(s) submitted to PMT for the selected period.');
    }
}
