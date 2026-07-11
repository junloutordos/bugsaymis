<?php

namespace App\Http\Controllers;

use App\Mail\IPCRAccomplishmentReturnedMail;
use App\Mail\IPCRDCReturnedFromPMTMail;
use App\Mail\IPCRRatedMail;
use App\Mail\IPCRReadyForEndorsementMail;
use App\Mail\IPCRSubmittedToHRMail;
use App\Mail\IPCRTargetsApprovedMail;
use App\Mail\IPCRTargetsReturnedMail;
use App\Models\Committee;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\IPCRRatingPeriod;
use App\Models\Office;
use App\Models\Role;
use App\Models\SpecialAssignment;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class DivisionChiefIPCRController extends Controller
{
    public function __construct(private IPCRWorkflowService $workflow)
    {
    }

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

        // Employees the user supervises through the academic chain
        // (AUH → teachers, ACIDAA → AUHs, CID Chief → ACIDAA)
        $supervisedUserIds = $this->workflow->supervisedUserIds($user);

        // Gate access
        if (!$user->hasAnyRole(['OCD', 'DivisionChief']) && !$isNonDCRater && $supervisedUserIds->isEmpty()) {
            abort(403, "You are not authorized to view this.");
        }

        $supervisedIpcrs = $supervisedUserIds->isEmpty() ? collect() : EmployeeIPCR::with(['user.division', 'period'])
            ->addSelect(['overall_average' => $avgSubquery])
            ->whereIn('user_id', $supervisedUserIds)
            ->orderBy('created_at', 'desc')
            ->get();

        // Academic supervisor / non-DC rater branch
        if (!$user->hasAnyRole(['OCD', 'DivisionChief'])) {
            $raterIpcrs = collect();
            if ($isNonDCRater) {
                $raterIpcrs = EmployeeIPCR::with(['user.division', 'period'])
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
            }

            $ipcrs = $supervisedIpcrs->merge($raterIpcrs)->unique('id')->values();

            $supervisedEmployees = $supervisedUserIds->isEmpty() ? collect() : User::whereIn('id', $supervisedUserIds)
                ->orderBy('name')
                ->get(['id', 'name', 'position', 'division_id']);

            return Inertia::render('PerformanceManagement/DivisionChiefIPCR', [
                'ipcrs'             => $ipcrs,
                'divisionEmployees' => $supervisedEmployees,
                'supervisor'        => $user,
                'ratingPeriods'     => $this->periodOptions($ipcrs),
                'openPeriods'       => IPCRRatingPeriod::open()->get(['id', 'label', 'year', 'semester', 'is_current']),
                'canEndorseAny'     => false,
            ]);
        }

        if ($user->hasAnyRole(['OCD', 'DivisionChief'])) {
            // --- Subordinate Division Chief IPCRs (visible only to OCD) ---
            $dcIpcrs = collect();
            if ($user->hasRole('OCD')) {
                $divisionChiefRoleId = Role::where('name', 'DivisionChief')->value('id');
                $dcIpcrs = EmployeeIPCR::with(['user.division', 'period'])
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
                $ownDivisionIpcrs = EmployeeIPCR::with(['user.division', 'period'])
                    ->addSelect(['overall_average' => $avgSubquery])
                    ->whereHas('user', fn($q) => $q->where('division_id', $divisionId)
                                                    ->where('id', '!=', $user->id))
                    ->orderBy('created_at', 'desc')
                    ->get();

                $ipcrs = $dcIpcrs->merge($ownDivisionIpcrs)->merge($supervisedIpcrs)->unique('id')->values();

                $divisionEmployees = User::where('division_id', $divisionId)
                    ->where('id', '!=', $user->id)
                    ->orderBy('name')
                    ->get(['id', 'name', 'position', 'division_id']);
            } else {
                if ($dcIpcrs->isEmpty() && $supervisedIpcrs->isEmpty()) {
                    abort(403, "You are not assigned to a division.");
                }
                $ipcrs             = $dcIpcrs->merge($supervisedIpcrs)->unique('id')->values();
                $divisionEmployees = collect();
            }
        } else {
            abort(403, "You are not authorized to view this.");
        }

        return Inertia::render('PerformanceManagement/DivisionChiefIPCR', [
            'ipcrs'             => $ipcrs,
            'divisionEmployees' => $divisionEmployees,
            'supervisor'        => $user->load('division'),
            'ratingPeriods'     => $this->periodOptions($ipcrs),
            'openPeriods'       => IPCRRatingPeriod::open()->get(['id', 'label', 'year', 'semester', 'is_current']),
            'canEndorseAny'     => true,
        ]);
    }

    /**
     * Distinct rating periods present in the visible IPCRs — id + label pairs
     * (id is null for legacy rows that only carry the free-text label).
     */
    private function periodOptions($ipcrs)
    {
        return $ipcrs
            ->map(fn ($ipcr) => [
                'id'    => $ipcr->rating_period_id,
                'label' => $ipcr->period?->label ?? $ipcr->rating_period,
            ])
            ->filter(fn ($p) => $p['label'])
            ->unique('label')
            ->sortBy('label')
            ->values();
    }

    /**
     * Show a single IPCR target with associated plans.
     */
    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division',
            'period',
            'plans.performance_indicator.agencyOutcome',
            'plans.offices',
            'plans.committees',
            'plans.specialAssignments',
        ])->findOrFail($id);

        $user = auth()->user();
        $canManageIpcr = $this->workflow->canManage($user, $ipcr);
        $canEndorse    = $this->workflow->canEndorse($user, $ipcr);

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

        $ocdUser = User::havingRole('OCD')->first();

        return Inertia::render('PerformanceManagement/DivisionChiefIPCRShow', [
            'ipcr'          => $ipcr,
            'plans'         => $plans,
            'employee'      => $ipcr->user,
            'supervisor'    => $user,
            'ocdUser'       => $ocdUser ? $ocdUser->only('name', 'position') : null,
            'canManageIpcr' => $canManageIpcr,
            'canEndorse'    => $canEndorse,
            'isMutable'     => $ipcr->isMutable(),
            'periodClosed'  => $ipcr->isPeriodClosed(),
            'hrDeadline'    => $ipcr->period?->hrDeadline(),
        ]);
    }

    /**
     * Approve IPCR target.
     */
    public function approveTargets(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertCanManage(auth()->user(), $employeeIPCR);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_TARGETS_APPROVED, [
            'target_approved_at' => now(),
        ], 'ipcr_targets_approved');

        $employeeIPCR->load('user');
        Mail::to($employeeIPCR->user->email)->send(new IPCRTargetsApprovedMail($employeeIPCR, $employeeIPCR->user->name));

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Targets approved successfully.');
    }

    /**
     * Disapprove IPCR targets and return to employee for revision.
     */
    public function disapproveTargets(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertCanManage(auth()->user(), $employeeIPCR);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_RETURNED, [], 'ipcr_targets_returned');

        $employeeIPCR->load('user');
        Mail::to($employeeIPCR->user->email)->send(new IPCRTargetsReturnedMail($employeeIPCR, $employeeIPCR->user->name));

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Targets returned to employee for revision.');
    }

    /**
     * Submit rated IPCR to PMT for review.
     */
    public function submitToPMT(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertCanEndorse(auth()->user(), $employeeIPCR);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_SUBMITTED_PMT, [
            'submitted_for_pmtreview_at' => now(),
        ], 'ipcr_submitted_to_pmt');

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted to PMT for review.');
    }

    /**
     * Return IPCR to employee after PMT requested revision.
     * Division Chief must provide remarks explaining why it is being returned.
     */
    public function returnFromPMT(Request $request, EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertCanEndorse(auth()->user(), $employeeIPCR);

        $request->validate([
            'remarks' => 'required|string|max:2000',
        ]);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_TARGETS_APPROVED, [
            'remarks'                    => $request->remarks,
            'submitted_for_rating_at'    => null,
            'submitted_rating_at'        => null,
            'submitted_for_pmtreview_at' => null,
        ], 'ipcr_returned_from_pmt');

        $employeeIPCR->load('user');
        Mail::to($employeeIPCR->user->email)->send(new IPCRDCReturnedFromPMTMail($employeeIPCR, $employeeIPCR->user->name));

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR returned to employee for revision.');
    }

    /**
     * Return submitted accomplishment to employee for revision.
     */
    public function returnAccomplishment(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertCanManage(auth()->user(), $employeeIPCR);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_TARGETS_APPROVED, [
            'submitted_for_rating_at' => null,
        ], 'ipcr_accomplishment_returned');

        $employeeIPCR->load('user');
        Mail::to($employeeIPCR->user->email)->send(new IPCRAccomplishmentReturnedMail($employeeIPCR, $employeeIPCR->user->name));

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Accomplishment returned to employee for revision.');
    }

    /**
     * Save a per-plan remark from the division chief during review.
     */
    public function savePlanRemark(Request $request, EmployeeIPCR $ipcr, $planId)
    {
        $this->workflow->assertMutable($ipcr);

        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        if (!$ipcr->plans()->where('work_distribution_plans.id', $planId)->exists()) {
            abort(404, 'This plan is not assigned to this IPCR.');
        }

        $user = auth()->user();
        $plan = \App\Models\WorkDistributionPlan::findOrFail($planId);
        if (! $this->workflow->canManage($user, $ipcr) && ! $this->workflow->canRatePlan($user, $ipcr, $plan)) {
            abort(403, 'You are not authorized to remark on this plan.');
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
        $this->workflow->assertMutable($ipcr);
        abort_if(
            $ipcr->status !== IPCRWorkflowService::STATUS_FOR_RATING,
            403,
            'Supervisor rating is only allowed while the IPCR is submitted for rating.'
        );

        // CSC SPMS 5-point scale. mov_link is deliberately NOT `url` and the
        // lengths mirror the employee side (updateSelfRating) — the DC modal
        // resubmits the employee's own saved values ("N/A", folder refs,
        // FL-synced text up to 1000 chars), which a stricter rule would 422.
        $request->validate([
            'accomplishment'  => 'nullable|string|max:1000',
            'mov_link'        => 'nullable|string|max:500',
            'sup_quality'     => 'nullable|integer|min:1|max:5',
            'sup_efficiency'  => 'nullable|integer|min:1|max:5',
            'sup_timeliness'  => 'nullable|integer|min:1|max:5',
        ]);

        // Ensure this plan belongs to THIS IPCR
        if (!$ipcr->plans()->where('work_distribution_plans.id', $planId)->exists()) {
            abort(404, "This plan is not assigned to this IPCR.");
        }

        // Authorization: check the logged-in user is the correct rater for this plan
        $user = auth()->user();
        $plan = \App\Models\WorkDistributionPlan::with(['offices', 'committees', 'specialAssignments'])->findOrFail($planId);

        if (! $this->workflow->canRatePlan($user, $ipcr, $plan)) {
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
        $user = auth()->user();
        $this->workflow->assertCanManage($user, $employeeIPCR);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_RATED, [
            'submitted_rating_at' => now(),
        ], 'ipcr_rated');

        $employeeIPCR->load('user');
        Mail::to($employeeIPCR->user->email)->send(new IPCRRatedMail($employeeIPCR, $employeeIPCR->user->name, $user));

        // Faculty chain: the rater (e.g. AUH) is not the endorser — tell the
        // Division Chief the IPCR is ready for the ministerial submit to PMT.
        $endorserId = Division::where('id', $employeeIPCR->user->division_id)->value('division_chief_id');
        if ($endorserId && $endorserId !== $user->id) {
            $endorser = User::find($endorserId);
            if ($endorser) {
                Mail::to($endorser->email)->send(new IPCRReadyForEndorsementMail($employeeIPCR, $endorser->name, $user));
            }
        }

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Rating recorded successfully.');
    }

    public function submitToHR(Request $request)
    {
        $request->validate([
            'rating_period_id' => 'nullable|exists:ipcr_rating_periods,id',
            'rating_period'    => 'required_without:rating_period_id|nullable|string',
        ]);

        $user = auth()->user();
        $divisionId = Division::where('division_chief_id', $user->id)->value('id') ?? $user->division_id;

        $ipcrs = EmployeeIPCR::where('status', IPCRWorkflowService::STATUS_RATED)
            ->when($request->rating_period_id,
                fn($q) => $q->where('rating_period_id', $request->rating_period_id),
                fn($q) => $q->where('rating_period', $request->rating_period))
            ->when($divisionId, fn($q) => $q->whereHas('user', fn($u) => $u->where('division_id', $divisionId)))
            ->get();

        foreach ($ipcrs as $ipcr) {
            $this->workflow->assertCanEndorse($user, $ipcr);
            $this->workflow->transition($ipcr, IPCRWorkflowService::STATUS_SUBMITTED_HR, [
                'submitted_to_hr_at' => now(),
            ], 'ipcr_submitted_to_hr');
        }

        User::havingRole('HR')->each(function ($hr) use ($ipcrs) {
            foreach ($ipcrs as $ipcr) {
                Mail::to($hr->email)->send(new IPCRSubmittedToHRMail($ipcr, $hr->name));
            }
        });

        return redirect()->back()->with('success', $ipcrs->count().' IPCR(s) submitted to HR for the selected period.');
    }

    /**
     * Save division chief comments / suggestions for improvement on an IPCR
     */
    public function saveComments(Request $request, EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertCanManage(auth()->user(), $employeeIPCR);
        $this->workflow->assertMutable($employeeIPCR);

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
