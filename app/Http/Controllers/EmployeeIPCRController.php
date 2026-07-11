<?php

namespace App\Http\Controllers;

use App\Mail\IPCRCreatedMail;
use App\Mail\IPCRSubmittedForRatingMail;
use App\Mail\IPCRSubmittedForReviewMail;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\AuditLogger;
use App\Services\PerformanceManagement\FacultyIPCRBaselineService;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class EmployeeIPCRController extends Controller
{
    // Statuses during which the employee may still edit the IPCR shell / plan list
    private const EDITABLE_STATUSES = [
        IPCRWorkflowService::STATUS_NEW_TARGET,
        IPCRWorkflowService::STATUS_RETURNED,
    ];

    private const PLAN_EDIT_STATUSES = [
        IPCRWorkflowService::STATUS_NEW_TARGET,
        IPCRWorkflowService::STATUS_FOR_REVIEW,
        IPCRWorkflowService::STATUS_RETURNED,
    ];

    public function __construct(private IPCRWorkflowService $workflow)
    {
    }

    /**
     * Display the authenticated user's IPCR targets
     */
    public function index()
    {
        $user = auth()->user();

        $ipcrs = EmployeeIPCR::with(['plans', 'period'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->each(fn ($ipcr) => $ipcr->setAttribute('is_mutable', $ipcr->isMutable()));

        $currentPeriod = IPCRRatingPeriod::current()->first();

        $workPlans = WorkDistributionPlan::with(['performance_indicator'])
            ->forFiscalYear($currentPeriod?->year)
            ->orderBy('id', 'asc')
            ->get();

        $ratingPeriods = IPCRRatingPeriod::open()
            ->get(['id', 'label', 'year', 'semester', 'status', 'start_date', 'end_date', 'is_current']);

        return Inertia::render('PerformanceManagement/EmployeeIPCR', [
            'ipcrs'         => $ipcrs,
            'workPlans'     => $workPlans,
            'ratingPeriods' => $ratingPeriods,
            'currentPeriod' => $currentPeriod,
            'isFaculty'     => $user->hasRole('Faculty') || (bool) $user->academic_unit_id,
        ]);
    }


    /**
     * Store a new IPCR target
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'rating_period_id' => 'required|exists:ipcr_rating_periods,id',
            'title'            => 'required|string|max:255',
            'remarks'          => 'nullable|string',
        ]);

        $period = IPCRRatingPeriod::findOrFail($data['rating_period_id']);
        $this->workflow->assertPeriodAcceptsNewTargets($period);
        $this->workflow->assertNoDuplicateForPeriod(auth()->id(), $period->id);

        $ipcr = EmployeeIPCR::create([
            'user_id'          => auth()->id(),
            'rating_period'    => $period->label, // dual-write during blue-green transition
            'rating_period_id' => $period->id,
            'title'            => $data['title'],
            'status'           => IPCRWorkflowService::STATUS_NEW_TARGET,
            'remarks'          => $data['remarks'] ?? null,
        ]);

        $supervisor = $this->workflow->immediateSupervisorFor(auth()->user());
        if ($supervisor) {
            $ipcr->load('user');
            Mail::to($supervisor->email)->send(new IPCRCreatedMail($ipcr, $supervisor->name));
        }

        AuditLogger::log([
            'action'         => 'ipcr_created',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $ipcr->id,
            'new_values'     => ['title' => $ipcr->title, 'rating_period' => $ipcr->rating_period],
        ]);

        return redirect()->back()->with('success', 'IPCR Target Created.');
    }

    /**
     * Update an IPCR target
     */
    public function update(Request $request, EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertOwner(auth()->user(), $employeeIPCR);
        $this->workflow->assertMutable($employeeIPCR);
        abort_unless(
            in_array($employeeIPCR->status, self::EDITABLE_STATUSES, true),
            403,
            'This IPCR can no longer be edited at its current stage.'
        );

        $data = $request->validate([
            'rating_period_id' => 'required|exists:ipcr_rating_periods,id',
            'title'            => 'required|string|max:255',
            'remarks'          => 'nullable|string',
        ]);

        $update = ['title' => $data['title'], 'remarks' => $data['remarks'] ?? null];

        if ((int) $data['rating_period_id'] !== (int) $employeeIPCR->rating_period_id) {
            $period = IPCRRatingPeriod::findOrFail($data['rating_period_id']);
            $this->workflow->assertPeriodAcceptsNewTargets($period);
            $this->workflow->assertNoDuplicateForPeriod(auth()->id(), $period->id, $employeeIPCR->id);
            $update['rating_period']    = $period->label;
            $update['rating_period_id'] = $period->id;
        }

        $employeeIPCR->update($update);

        AuditLogger::log([
            'action'         => 'ipcr_updated',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['title' => $employeeIPCR->title, 'status' => $employeeIPCR->status],
        ]);

        return redirect()->back()->with('success', 'IPCR Target Updated.');
    }

    /**
     * Delete an IPCR target
     */
    public function destroy(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertOwner(auth()->user(), $employeeIPCR);
        $this->workflow->assertMutable($employeeIPCR);
        abort_unless(
            in_array($employeeIPCR->status, self::EDITABLE_STATUSES, true),
            403,
            'Only IPCRs that are new or returned for revision can be deleted.'
        );

        $employeeIPCR->delete();

        AuditLogger::log([
            'action'         => 'ipcr_deleted',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['title' => $employeeIPCR->title],
        ]);

        return redirect()->back()->with('success', 'IPCR Target Deleted.');
    }

    /**
     * Assign plans to an IPCR target
     */
    public function addPlans(Request $request, $ipcrId)
    {
        $ipcr = EmployeeIPCR::findOrFail($ipcrId);
        $this->guardPlanEditing($ipcr);

        $data = $request->validate([
            'plan_ids'   => 'required|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $ipcr->plans()->syncWithoutDetaching($this->scopePlanIds($ipcr, $data['plan_ids']));

        return redirect()->back()->with('success', 'Plans assigned successfully.');
    }

    /**
     * Replace existing plans
     */
    public function syncPlans(Request $request, $ipcrId)
    {
        $ipcr = EmployeeIPCR::findOrFail($ipcrId);
        $this->guardPlanEditing($ipcr);

        $data = $request->validate([
            'plan_ids'   => 'required|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $ipcr->plans()->sync($this->scopePlanIds($ipcr, $data['plan_ids']));

        return redirect()->back()->with('success', 'Plans synced successfully.');
    }

    private function guardPlanEditing(EmployeeIPCR $ipcr): void
    {
        $this->workflow->assertOwner(auth()->user(), $ipcr);
        $this->workflow->assertMutable($ipcr);
        abort_unless(
            in_array($ipcr->status, self::PLAN_EDIT_STATUSES, true),
            403,
            'Plans can only be changed before targets are approved.'
        );
    }

    /** Keep only plan IDs valid for the IPCR's fiscal year (NULL = all years). */
    private function scopePlanIds(EmployeeIPCR $ipcr, array $planIds): array
    {
        return WorkDistributionPlan::whereIn('id', $planIds)
            ->forFiscalYear($ipcr->period?->year)
            ->pluck('id')
            ->all();
    }

    /**
     * Show IPCR with plans
     */
    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division.divisionchief',
            'period',
            'plans.performance_indicator.agencyOutcome'
        ])->findOrFail($id);

        $workPlans = WorkDistributionPlan::with(['performance_indicator'])
            ->forFiscalYear($ipcr->period?->year)
            ->orderBy('id', 'asc')
            ->get();

        // Immediate supervisor per the SPMS chain (AUH/ACIDAA/CID Chief for
        // faculty, Division Chief otherwise); a Division Chief's head is the
        // Campus Director (OCD).
        $supervisor = $this->workflow->immediateSupervisorFor($ipcr->user)
            ?? ($ipcr->user->hasRole('DivisionChief') ? \App\Models\User::havingRole('OCD')->first() : null);

        // Load all pivot IDs for this IPCR (keyed by plan_id)
        $ipcrPlanIds = \App\Models\EmployeeIPCRPlan::where('ipcr_id', $ipcr->id)
            ->pluck('id', 'plan_id');

        // Load all daily accomplishments for those pivots in one query
        $accomplishmentsByPivot = \App\Models\Accomplishment::with('photos')
            ->whereIn('ipcr_plan_id', $ipcrPlanIds->values())
            ->orderBy('accomplishment_date', 'desc')
            ->get()
            ->groupBy('ipcr_plan_id');

        // Append accomplishments + count to each plan
        $plans = $ipcr->plans->map(function ($plan) use ($ipcrPlanIds, $accomplishmentsByPivot) {
            $pivotId                   = $ipcrPlanIds[$plan->id] ?? null;
            $accs                      = $pivotId ? ($accomplishmentsByPivot[$pivotId] ?? collect()) : collect();
            $plan->ipcr_plan_id        = $pivotId;
            $plan->accomplishments     = $accs->values();
            $plan->accomplishments_count = $accs->count();
            return $plan;
        });

        $ocdUser   = \App\Models\User::havingRole('OCD')->first();
        $isFaculty = $ipcr->user->hasRole('Faculty');

        // For faculty (CID teachers), surface WDP plans linked to their active FL committee assignments
        $suggestedPlanIds = [];
        if ($isFaculty) {
            $committeeIds = FacultyCommitteeAssignment::where('user_id', $ipcr->user->id)
                ->where('status', 'active')
                ->whereNotNull('committee_id')
                ->pluck('committee_id');

            if ($committeeIds->isNotEmpty()) {
                $suggestedPlanIds = DB::table('committee_work_distribution_plan')
                    ->whereIn('committee_id', $committeeIds)
                    ->pluck('work_distribution_plan_id')
                    ->unique()
                    ->values()
                    ->toArray();
            }
        }

        return Inertia::render('PerformanceManagement/EmployeeIPCRShow', [
            'ipcr'             => $ipcr,
            'employee'         => $ipcr->user,
            'supervisor'       => $supervisor,
            'ocdUser'          => $ocdUser ? ['name' => $ocdUser->name, 'position' => $ocdUser->position] : null,
            'plans'            => $plans,
            'workPlans'        => $workPlans,
            'isFaculty'        => $isFaculty,
            'suggestedPlanIds' => $suggestedPlanIds,
            'isOwner'          => $ipcr->user_id === auth()->id(),
            'isMutable'        => $ipcr->isMutable(),
            'periodClosed'     => $ipcr->isPeriodClosed(),
            'hrDeadline'       => $ipcr->period?->hrDeadline(),
        ]);
    }


    /**
     * Update self ratings/accomplishment – CORRECTED VERSION
     * This now updates ONLY the pivot of THIS IPCR + THIS plan
     */
    public function updateSelfRating(Request $request, EmployeeIPCR $ipcr, $planId)
    {
        abort_if($ipcr->user_id !== auth()->id(), 403);
        $this->workflow->assertMutable($ipcr);
        abort_if($ipcr->status !== IPCRWorkflowService::STATUS_TARGETS_APPROVED, 403, 'Self-rating is only allowed when targets are approved.');

        // CSC SPMS 5-point scale
        $request->validate([
            'accomplishment'  => 'nullable|string|max:255',
            'mov_link'        => 'nullable|string|max:500',
            'self_quality'    => 'nullable|integer|min:1|max:5',
            'self_efficiency' => 'nullable|integer|min:1|max:5',
            'self_timeliness' => 'nullable|integer|min:1|max:5',
        ]);

        // Ensure this plan belongs to THIS IPCR
        if (!$ipcr->plans()->where('work_distribution_plans.id', $planId)->exists()) {
            abort(404, "This plan is not assigned to this IPCR.");
        }

        // Compute average
        $ratings = collect([
            $request->self_quality,
            $request->self_efficiency,
            $request->self_timeliness
        ])->filter();

        $selfAverage = $ratings->count() ? round($ratings->avg(), 2) : null;

        // Update ONLY this IPCR's pivot record
        $ipcr->plans()->updateExistingPivot($planId, [
            'accomplishment'  => $request->accomplishment,
            'mov_link'        => $request->mov_link,
            'self_quality'    => $request->self_quality,
            'self_efficiency' => $request->self_efficiency,
            'self_timeliness' => $request->self_timeliness,
            'self_average'    => $selfAverage,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Accomplishment and ratings saved successfully.');
    }


    /**
     * Pull accomplishments (and supervisor ratings if already given) from Faculty Loading
     * committee assignments into this IPCR's plan pivots.
     * Only available to faculty members before the IPCR is submitted for rating.
     */
    public function pullFLAccomplishments(EmployeeIPCR $employeeIPCR): RedirectResponse
    {
        abort_if($employeeIPCR->user_id !== auth()->id(), 403);
        $this->workflow->assertMutable($employeeIPCR);
        abort_if(
            ! in_array($employeeIPCR->status, ['New Target', 'For Review', 'Targets Approved', 'Returned for Revision']),
            422,
            'Faculty Loading sync is not available at this stage.'
        );

        $ipcrPlanIds = $employeeIPCR->plans()->pluck('work_distribution_plans.id');

        if ($ipcrPlanIds->isEmpty()) {
            return back()->with('success', 'No plans in this IPCR to sync.');
        }

        $assignments = FacultyCommitteeAssignment::with('accomplishments')
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->whereNotNull('committee_id')
            ->get();

        $updated = 0;
        foreach ($assignments as $assignment) {
            foreach ($assignment->accomplishments as $acc) {
                if (! $ipcrPlanIds->contains($acc->work_distribution_plan_id)) {
                    continue;
                }

                $pivot = ['accomplishment' => $acc->accomplishment, 'mov_link' => $acc->mov_link];

                // Carry over supervisor ratings if the chairperson has already rated
                if (! is_null($acc->sup_average)) {
                    $pivot['sup_quality']    = $acc->sup_quality;
                    $pivot['sup_efficiency'] = $acc->sup_efficiency;
                    $pivot['sup_timeliness'] = $acc->sup_timeliness;
                    $pivot['sup_average']    = $acc->sup_average;
                }

                $employeeIPCR->plans()->updateExistingPivot($acc->work_distribution_plan_id, $pivot);
                $updated++;
            }
        }

        return back()->with('success', $updated > 0
            ? "{$updated} plan(s) synced from Faculty Loading."
            : 'No Faculty Loading accomplishments found to sync. Make sure your committee chairperson has rated your work in the Faculty Loading module.');
    }

    /**
     * Generate the faculty baseline from Faculty Loading: attach framework /
     * committee / personnel plans and personalize individual targets.
     */
    public function generateFacultyTargets(EmployeeIPCR $employeeIPCR, FacultyIPCRBaselineService $baseline)
    {
        $this->guardPlanEditing($employeeIPCR);

        $user = auth()->user();
        abort_unless(
            $user->hasRole('Faculty') || $user->academic_unit_id,
            403,
            'The Faculty Loading baseline is only available to faculty members.'
        );

        $summary = $baseline->generate($employeeIPCR);

        AuditLogger::log([
            'action'         => 'ipcr_faculty_baseline_generated',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => $summary,
        ]);

        return back()->with('success', $summary['attached'] || $summary['personalized']
            ? "Baseline generated from your Faculty Load: {$summary['attached']} plan(s) attached, {$summary['personalized']} individual target(s) filled in."
            : 'No new plans or targets were generated — check that your faculty load exists for this period and that faculty plans are tagged with a load source.');
    }

    /**
     * Owner edits the personalized target of one plan row while drafting.
     */
    public function updateIndividualTarget(Request $request, EmployeeIPCR $employeeIPCR, $planId)
    {
        $this->workflow->assertOwner(auth()->user(), $employeeIPCR);
        $this->workflow->assertMutable($employeeIPCR);
        abort_unless(
            in_array($employeeIPCR->status, self::PLAN_EDIT_STATUSES, true),
            403,
            'Individual targets can only be edited before targets are approved.'
        );

        $request->validate([
            'individual_target' => 'nullable|string|max:2000',
        ]);

        if (!$employeeIPCR->plans()->where('work_distribution_plans.id', $planId)->exists()) {
            abort(404, 'This plan is not assigned to this IPCR.');
        }

        $employeeIPCR->plans()->updateExistingPivot($planId, [
            'individual_target' => $request->individual_target,
        ]);

        return back()->with('success', 'Individual target saved.');
    }

    public function submitForReview(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertOwner(auth()->user(), $employeeIPCR);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_FOR_REVIEW, [
            'submitted_for_review_at' => now(),
        ], 'ipcr_submitted_for_review');

        $employeeIPCR->load('user');
        $dc = $this->workflow->immediateSupervisorFor($employeeIPCR->user);
        if ($dc) {
            Mail::to($dc->email)->send(new IPCRSubmittedForReviewMail($employeeIPCR, $dc->name));
        }

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted for review successfully.');
    }

    public function submitForRating(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertOwner(auth()->user(), $employeeIPCR);

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_FOR_RATING, [
            'submitted_for_rating_at' => now(),
        ], 'ipcr_submitted_for_rating');

        $employeeIPCR->load('user');
        $dc = $this->workflow->immediateSupervisorFor($employeeIPCR->user);
        if ($dc) {
            Mail::to($dc->email)->send(new IPCRSubmittedForRatingMail($employeeIPCR, $dc->name));
        }

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted for rating successfully.');
    }

    /**
     * Remove a plan from an IPCR (only allowed when returned for revision).
     */
    public function removePlan(EmployeeIPCR $employeeIPCR, $planId)
    {
        $this->workflow->assertOwner(auth()->user(), $employeeIPCR);
        $this->workflow->assertMutable($employeeIPCR);

        if ($employeeIPCR->status !== IPCRWorkflowService::STATUS_RETURNED) {
            abort(403, 'Plans can only be removed when the IPCR is returned for revision.');
        }

        $employeeIPCR->plans()->detach($planId);

        return redirect()->back()->with('success', 'Plan removed successfully.');
    }

    /**
     * Resubmit an IPCR for review after revision (only from Returned for Revision).
     */
    public function resubmit(EmployeeIPCR $employeeIPCR)
    {
        $this->workflow->assertOwner(auth()->user(), $employeeIPCR);

        if ($employeeIPCR->status !== IPCRWorkflowService::STATUS_RETURNED) {
            abort(403, 'Only IPCRs returned for revision can be resubmitted.');
        }

        $this->workflow->transition($employeeIPCR, IPCRWorkflowService::STATUS_FOR_REVIEW, [
            'submitted_for_review_at' => now(),
        ], 'ipcr_resubmitted');

        $employeeIPCR->load('user');
        $dc = $this->workflow->immediateSupervisorFor($employeeIPCR->user);
        if ($dc) {
            Mail::to($dc->email)->send(new IPCRSubmittedForReviewMail($employeeIPCR, $dc->name));
        }

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR resubmitted for review.');
    }
}
