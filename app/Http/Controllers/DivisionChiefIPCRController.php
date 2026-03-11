<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\Office;
use App\Models\Role;
use App\Models\SpecialAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DivisionChiefIPCRController extends Controller
{
    /**
     * Display all IPCRs of subordinates under the division chief's division.
     */
    public function index()
    {
        $user = auth()->user();

        $avgSubquery = DB::table('employee_ipcrs_plan')
            ->selectRaw('ROUND(AVG(sup_average), 2)')
            ->whereColumn('ipcr_id', 'employee_ipcrs.id')
            ->whereNotNull('sup_average');

        // Determine what non-DC rater roles the user holds
        $unitHeadOfficeIds        = Office::where('unit_head', $user->id)->pluck('id');
        $headedCommitteeIds       = Committee::where('head_id', $user->id)->pluck('id');
        $coordinatedAssignmentIds = SpecialAssignment::where('coordinator_id', $user->id)->pluck('id');
        $isNonDCRater = $unitHeadOfficeIds->isNotEmpty()
                     || $headedCommitteeIds->isNotEmpty()
                     || $coordinatedAssignmentIds->isNotEmpty();

        // Gate access
        if (!$user->hasAnyRole(['OCD', 'DivisionChief']) && !$isNonDCRater) {
            abort(403, "You are not authorized to view this.");
        }

        // Non-DC rater branch: show IPCRs containing plans they can rate
        if ($isNonDCRater && !$user->hasAnyRole(['OCD', 'DivisionChief'])) {
            $ipcrs = EmployeeIPCR::with(['user.division'])
                ->addSelect(['overall_average' => $avgSubquery])
                ->whereHas('plans', fn($q) =>
                    $q->where(function ($q2) use ($unitHeadOfficeIds, $headedCommitteeIds, $coordinatedAssignmentIds) {
                        $q2->where(fn($q3) =>
                            $q3->where('rated_by', 'Unit Head')
                               ->whereHas('offices', fn($oq) => $oq->whereIn('offices.id', $unitHeadOfficeIds))
                        )
                        ->orWhere(fn($q3) =>
                            $q3->where('rated_by', 'Committee Head')
                               ->whereHas('committees', fn($cq) => $cq->whereIn('committees.id', $headedCommitteeIds))
                        )
                        ->orWhere(fn($q3) =>
                            $q3->where('rated_by', 'Coordinator')
                               ->whereHas('specialAssignments', fn($aq) => $aq->whereIn('special_assignments.id', $coordinatedAssignmentIds))
                        );
                    })
                )
                ->whereIn('status', [
                    'Submitted for Rating',
                    'Rated & For PMT Review',
                    'Submitted to PMT',
                    'PMT Returned for Revision',
                    'Approved by PMT',
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            $ratingPeriods = $ipcrs->pluck('rating_period')->filter()->unique()->sort()->values();

            return Inertia::render('PerformanceManagement/DivisionChiefIPCR', [
                'ipcrs'             => $ipcrs,
                'divisionEmployees' => collect(),
                'supervisor'        => $user,
                'ratingPeriods'     => $ratingPeriods,
            ]);
        }

        if ($user->hasAnyRole(['OCD', 'DivisionChief'])) {
            // --- Subordinate Division Chief IPCRs (visible only to OCD) ---
            $dcIpcrs = collect();
            if ($user->hasRole('OCD')) {
                $divisionChiefRoleId = Role::where('name', 'DivisionChief')->value('id');
                $dcIpcrs = EmployeeIPCR::with(['user.division'])
                    ->addSelect(['overall_average' => $avgSubquery])
                    ->whereHas('user', fn($q) => $q->whereRaw('FIND_IN_SET(?, role_id)', [$divisionChiefRoleId])
                                                    ->where('id', '!=', $user->id))
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            // --- Own division's employees (applies to both OCD acting as DC and regular DivisionChief) ---
            $divisionId = Division::where('division_chief_id', $user->id)->value('id')
                        ?? $user->division_id;

            if ($divisionId) {
                $ownDivisionIpcrs = EmployeeIPCR::with(['user.division'])
                    ->addSelect(['overall_average' => $avgSubquery])
                    ->whereHas('user', fn($q) => $q->where('division_id', $divisionId)
                                                    ->where('id', '!=', $user->id))
                    ->orderBy('created_at', 'desc')
                    ->get();

                $ipcrs = $dcIpcrs->merge($ownDivisionIpcrs)->unique('id')->values();

                $divisionEmployees = User::where('division_id', $divisionId)
                    ->where('id', '!=', $user->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'position', 'division_id']);
            } else {
                if ($dcIpcrs->isEmpty()) {
                    abort(403, "You are not assigned to a division.");
                }
                $ipcrs             = $dcIpcrs;
                $divisionEmployees = collect();
            }
        } else {
            abort(403, "You are not authorized to view this.");
        }

        // Distinct rating periods from the visible IPCRs (for the memo report dropdown)
        $ratingPeriods = $ipcrs->pluck('rating_period')->filter()->unique()->sort()->values();

        return Inertia::render('PerformanceManagement/DivisionChiefIPCR', [
            'ipcrs'             => $ipcrs,
            'divisionEmployees' => $divisionEmployees,
            'supervisor'        => $user->load('division'),
            'ratingPeriods'     => $ratingPeriods,
        ]);
    }

    /**
     * Show a single IPCR target with associated plans.
     */
    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division',
            'plans.performance_indicator.agencyOutcome',
            'plans.offices',
            'plans.committees',
            'plans.specialAssignments',
        ])->findOrFail($id);

        $user = auth()->user();
        $employeeDivisionChiefId = $ipcr->user->division->division_chief_id ?? null;
        $canManageIpcr = $user->hasRole('OCD') || $user->id == $employeeDivisionChiefId;

        // Bulk-load accomplishments for all plans (2 queries, avoids N+1)
        $ipcrPlanIds = \App\Models\EmployeeIPCRPlan::where('ipcr_id', $ipcr->id)
            ->pluck('id', 'plan_id');

        $accomplishmentsByPivot = \App\Models\Accomplishment::with('photos')
            ->whereIn('ipcr_plan_id', $ipcrPlanIds->values())
            ->orderBy('accomplishment_date', 'desc')
            ->get()
            ->groupBy('ipcr_plan_id');

        $plans = $ipcr->plans->map(function ($plan) use ($ipcrPlanIds, $accomplishmentsByPivot) {
            $pivotId = $ipcrPlanIds[$plan->id] ?? null;
            $accs = $pivotId ? ($accomplishmentsByPivot[$pivotId] ?? collect()) : collect();
            $plan->ipcr_plan_id        = $pivotId;
            $plan->accomplishments     = $accs->values();
            $plan->accomplishments_count = $accs->count();
            return $plan;
        });

        return Inertia::render('PerformanceManagement/DivisionChiefIPCRShow', [
            'ipcr'          => $ipcr,
            'plans'         => $plans,
            'employee'      => $ipcr->user,
            'supervisor'    => $user,
            'canManageIpcr' => $canManageIpcr,
        ]);
    }

    /**
     * Approve IPCR target.
     */
    public function approveTargets(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'Targets Approved',
            'target_approved_at' => now(),
        ]);

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Targets approved successfully.');
    }

    /**
     * Disapprove IPCR targets and return to employee for revision.
     */
    public function disapproveTargets(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'Returned for Revision',
        ]);

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Targets returned to employee for revision.');
    }

    /**
     * Submit rated IPCR to PMT for review.
     */
    public function submitToPMT(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'Submitted to PMT',
            'submitted_for_pmtreview_at' => now(),
        ]);

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted to PMT for review.');
    }

    /**
     * Return IPCR to employee after PMT requested revision.
     * Division Chief must provide remarks explaining why it is being returned.
     */
    public function returnFromPMT(Request $request, EmployeeIPCR $employeeIPCR)
    {
        $request->validate([
            'remarks' => 'required|string|max:2000',
        ]);

        $employeeIPCR->update([
            'status'                     => 'Targets Approved',
            'remarks'                    => $request->remarks,
            'submitted_for_rating_at'    => null,
            'submitted_rating_at'        => null,
            'submitted_for_pmtreview_at' => null,
        ]);

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR returned to employee for revision.');
    }

    /**
     * Return submitted accomplishment to employee for revision.
     */
    public function returnAccomplishment(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'Targets Approved',
            'submitted_for_rating_at' => null,
        ]);

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Accomplishment returned to employee for revision.');
    }

    /**
     * Save a per-plan remark from the division chief during review.
     */
    public function savePlanRemark(Request $request, EmployeeIPCR $ipcr, $planId)
    {
        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        if (!$ipcr->plans()->where('work_distribution_plans.id', $planId)->exists()) {
            abort(404, 'This plan is not assigned to this IPCR.');
        }

        $ipcr->plans()->updateExistingPivot($planId, [
            'remarks' => $request->remarks,
        ]);

        return redirect()->back()->with('success', 'Remark saved.');
    }

    /**
     * Rate accomplishments (quality, efficiency, timeliness, average)
     */
   public function rateIPCRPlan(Request $request, EmployeeIPCR $ipcr, $planId)
    {
        $request->validate([
            'accomplishment'  => 'nullable|string|max:255',
            'mov_link'        => 'nullable|url|max:255',
            'sup_quality'     => 'nullable|numeric|min:0|max:100',
            'sup_efficiency'  => 'nullable|numeric|min:0|max:100',
            'sup_timeliness'  => 'nullable|numeric|min:0|max:100',
        ]);

        // Ensure this plan belongs to THIS IPCR
        if (!$ipcr->plans()->where('work_distribution_plans.id', $planId)->exists()) {
            abort(404, "This plan is not assigned to this IPCR.");
        }

        // Authorization: check the logged-in user is the correct rater for this plan
        $user = auth()->user();
        $plan = \App\Models\WorkDistributionPlan::with(['offices', 'committees', 'specialAssignments'])->findOrFail($planId);
        $divisionId = Division::where('division_chief_id', $user->id)->value('id') ?? $user->division_id;

        $isDCForEmployee = $divisionId && $ipcr->user->division_id == $divisionId;
        $canRate = $user->hasRole('OCD') || $isDCForEmployee || match ($plan->rated_by) {
            'Unit Head'      => $plan->offices->contains(fn($o) => $o->unit_head == $user->id),
            'Committee Head' => $plan->committees->contains(fn($c) => $c->head_id == $user->id),
            'Coordinator'    => $plan->specialAssignments->contains(fn($a) => $a->coordinator_id == $user->id),
            default          => false,
        };

        if (!$canRate) {
            abort(403, 'You are not authorized to rate this plan.');
        }

        // Compute average for supervisor ratings
        $ratings = collect([
            $request->sup_quality,
            $request->sup_efficiency,
            $request->sup_timeliness
        ])->filter();

        $supAverage = $ratings->count() ? round($ratings->avg(), 2) : null;

        // Update ONLY this IPCR's pivot record for supervisor ratings
        $ipcr->plans()->updateExistingPivot($planId, [
            'accomplishment'  => $request->accomplishment,
            'mov_link'        => $request->mov_link,
            'sup_quality'     => $request->sup_quality,
            'sup_efficiency'  => $request->sup_efficiency,
            'sup_timeliness'  => $request->sup_timeliness,
            'sup_average'     => $supAverage,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Accomplishment and supervisor ratings saved successfully.');
    }
    public function saveRatings(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'Rated & For PMT Review',
            'submitted_rating_at' => now(),
        ]);
        
        $employeeIPCR->save();

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Rating recorded successfully.');
    }

    /**
     * Save division chief comments / suggestions for improvement on an IPCR
     */
    public function saveComments(Request $request, EmployeeIPCR $employeeIPCR)
    {
        $data = $request->validate([
            'division_comments' => 'nullable|string|max:2000',
        ]);

        $employeeIPCR->update([
            'remarks' => $data['division_comments'] ?? null,
        ]);

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Comments saved successfully.');
    }

}
