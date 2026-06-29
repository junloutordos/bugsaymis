<?php

namespace App\Http\Controllers;

use App\Mail\IPCRCreatedMail;
use App\Mail\IPCRSubmittedForRatingMail;
use App\Mail\IPCRSubmittedForReviewMail;
use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class EmployeeIPCRController extends Controller
{
    /**
     * Display the authenticated user's IPCR targets
     */
    public function index()
    {
        $user = auth()->user();

        $ipcrs = EmployeeIPCR::with('plans')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $workPlans = WorkDistributionPlan::with(['performance_indicator'])
            ->orderBy('id', 'asc')
            ->get();

        $ratingPeriods = IPCRRatingPeriod::active()->pluck('label');

        return Inertia::render('PerformanceManagement/EmployeeIPCR', [
            'ipcrs'         => $ipcrs,
            'workPlans'     => $workPlans,
            'ratingPeriods' => $ratingPeriods,
        ]);
    }


    /**
     * Store a new IPCR target
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'rating_period' => 'required|string|max:255',
            'title'         => 'required|string|max:255',
            'remarks'       => 'nullable|string',
        ]);

        $ipcr = EmployeeIPCR::create([
            'user_id'       => auth()->id(),
            'rating_period' => $data['rating_period'],
            'title'         => $data['title'],
            'status'        => 'New Target',
            'remarks'       => $data['remarks'] ?? null,
        ]);

        $dc = $this->resolveDivisionChief(auth()->user());
        if ($dc) {
            $ipcr->load('user');
            Mail::to($dc->email)->send(new IPCRCreatedMail($ipcr, $dc->name));
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
        $data = $request->validate([
            'rating_period' => 'required|string|max:255',
            'title'         => 'required|string|max:255',
            'status'        => 'required|string|max:50',
            'remarks'       => 'nullable|string',
        ]);

        $employeeIPCR->update($data);

        $dc = $this->resolveDivisionChief(auth()->user());
        if ($dc) {
            $employeeIPCR->load('user');
            Mail::to($dc->email)->send(new IPCRCreatedMail($employeeIPCR, $dc->name));
        }

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
        $employeeIPCR->delete();

        return redirect()->back()->with('success', 'IPCR Target Deleted.');
    }

    /**
     * Assign plans to an IPCR target
     */
    public function addPlans(Request $request, $ipcrId)
    {
        $data = $request->validate([
            'plan_ids'   => 'required|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $ipcr = EmployeeIPCR::findOrFail($ipcrId);

        $ipcr->plans()->syncWithoutDetaching($data['plan_ids']);

        return redirect()->back()->with('success', 'Plans assigned successfully.');
    }

    /**
     * Replace existing plans
     */
    public function syncPlans(Request $request, $ipcrId)
    {
        $data = $request->validate([
            'plan_ids'   => 'required|array',
            'plan_ids.*' => 'exists:work_distribution_plans,id',
        ]);

        $ipcr = EmployeeIPCR::findOrFail($ipcrId);

        $ipcr->plans()->sync($data['plan_ids']);

        return redirect()->back()->with('success', 'Plans synced successfully.');
    }

    /**
     * Show IPCR with plans
     */
    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'user.division.divisionchief',
            'plans.performance_indicator.agencyOutcome'
        ])->findOrFail($id);

        $workPlans = WorkDistributionPlan::with(['performance_indicator'])
            ->orderBy('id', 'asc')
            ->get();

        // If the IPCR owner is a Division Chief, their immediate head is the Campus Director (OCD)
        $supervisor = $ipcr->user->hasRole('DivisionChief')
            ? \App\Models\User::havingRole('OCD')->first()
            : ($ipcr->user->division->divisionchief ?? null);

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
        ]);
    }


    /**
     * Update self ratings/accomplishment – CORRECTED VERSION
     * This now updates ONLY the pivot of THIS IPCR + THIS plan
     */
    public function updateSelfRating(Request $request, EmployeeIPCR $ipcr, $planId)
    {
        abort_if($ipcr->user_id !== auth()->id(), 403);
        abort_if($ipcr->status !== 'Targets Approved', 403, 'Self-rating is only allowed when targets are approved.');

        $request->validate([
            'accomplishment'  => 'nullable|string|max:255',
            'mov_link'        => 'nullable|string|max:500',
            'self_quality'    => 'nullable|numeric|min:0|max:100',
            'self_efficiency' => 'nullable|numeric|min:0|max:100',
            'self_timeliness' => 'nullable|numeric|min:0|max:100',
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

    private function resolveDivisionChief(User $employee): ?User
    {
        $divisionId = $employee->division_id;
        if (!$divisionId) return null;
        $chiefId = Division::where('id', $divisionId)->value('division_chief_id');
        return $chiefId ? User::find($chiefId) : null;
    }

    public function submitForReview(EmployeeIPCR $employeeIPCR)
    {
        abort_if($employeeIPCR->user_id !== auth()->id(), 403);

        $employeeIPCR->update([
            'status' => 'For Review',
            'submitted_for_review_at' => now(),
        ]);

        $employeeIPCR->load('user');
        $dc = $this->resolveDivisionChief($employeeIPCR->user);
        if ($dc) {
            Mail::to($dc->email)->send(new IPCRSubmittedForReviewMail($employeeIPCR, $dc->name));
        }

        AuditLogger::log([
            'action'         => 'ipcr_submitted_for_review',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['status' => 'For Review'],
        ]);

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted for review successfully.');
    }

    public function submitForRating(EmployeeIPCR $employeeIPCR)
    {
        abort_if($employeeIPCR->user_id !== auth()->id(), 403);

        $employeeIPCR->update([
            'status' => 'Submitted for Rating',
            'submitted_for_rating_at' => now(),
        ]);

        $employeeIPCR->load('user');
        $dc = $this->resolveDivisionChief($employeeIPCR->user);
        if ($dc) {
            Mail::to($dc->email)->send(new IPCRSubmittedForRatingMail($employeeIPCR, $dc->name));
        }

        AuditLogger::log([
            'action'         => 'ipcr_submitted_for_rating',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['status' => 'Submitted for Rating'],
        ]);

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted for rating successfully.');
    }

    /**
     * Remove a plan from an IPCR (only allowed when returned for revision).
     */
    public function removePlan(EmployeeIPCR $employeeIPCR, $planId)
    {
        if ($employeeIPCR->user_id !== auth()->id()) {
            abort(403);
        }

        if ($employeeIPCR->status !== 'Returned for Revision') {
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
        if ($employeeIPCR->user_id !== auth()->id()) {
            abort(403);
        }

        if ($employeeIPCR->status !== 'Returned for Revision') {
            abort(403, 'Only IPCRs returned for revision can be resubmitted.');
        }

        $employeeIPCR->update([
            'status' => 'For Review',
            'submitted_for_review_at' => now(),
        ]);

        $employeeIPCR->load('user');
        $dc = $this->resolveDivisionChief($employeeIPCR->user);
        if ($dc) {
            Mail::to($dc->email)->send(new IPCRSubmittedForReviewMail($employeeIPCR, $dc->name));
        }

        AuditLogger::log([
            'action'         => 'ipcr_resubmitted',
            'auditable_type' => EmployeeIPCR::class,
            'auditable_id'   => $employeeIPCR->id,
            'new_values'     => ['status' => 'For Review'],
        ]);

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR resubmitted for review.');
    }
}
