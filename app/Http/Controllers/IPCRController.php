<?php

namespace App\Http\Controllers;

use App\Models\IPCR;
use App\Models\WorkDistributionPlan;
use App\Models\User;
use App\Models\Division;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IPCRController extends Controller
{
    /**
     * Employee: Display all assigned plans for the authenticated user.
     */
    public function index()
{
    $user = auth()->user();

    // Load ALL Work Distribution Plans
    $plans = WorkDistributionPlan::with([
        // Load only the IPCR belonging to the authenticated user (if exists)
        'ipcrs' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        },

        // Load PI + Agency Outcome
        'performanceIndicator',
        'performanceIndicator.agencyOutcome',
    ])
    ->latest('created_at')
    ->get();

    return Inertia::render('PerformanceManagement/IPCR', [
        'plans' => $plans,
    ]);
}



    /**
     * Supervisor: Display all employees under the supervisor/division chief
     * along with their IPCR plans.
     */
    public function dcIndex()
{
    $supervisor = auth()->user();

    $division = Division::with([
        'employees.ipcrs' => function ($q) {
            $q->with('workDistributionPlan');
        }
    ])
    ->where('division_chief_id', $supervisor->id)
    ->first();

    if (!$division) {
        return back()->with('error', 'You are not assigned as a Division Chief.');
    }

    // Subordinates only (exclude supervisor)
    $subordinates = $division->employees->where('id', '=', $supervisor->id)->values();

    // Pending targets
    $pendingTargets = IPCR::with('user')
        ->where('target_status', 'submitted')
        ->whereIn('user_id', $subordinates->pluck('id'))
        ->get();

    // Pending accomplishment reviews
    $pendingAccomplishments = IPCR::with('user')
        ->whereNotNull('accomplishment')
        ->whereNull('supervisor_rating')
        ->whereIn('user_id', $subordinates->pluck('id'))
        ->get();

    return Inertia::render('PerformanceManagement/DivisionChiefIPCR', [
        'subordinates' => $subordinates,
        'pendingTargets' => $pendingTargets,
        'pendingAccomplishments' => $pendingAccomplishments,
        'division' => $division,
    ]);
}




    /**
     * Submit or update IPCR target for a plan (employee action)
     */
    public function submitTarget(Request $request, $planId)
    {
        $request->validate([
            'target' => 'required|string|max:1000'
        ]);

        $ipcr = IPCR::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'work_distribution_plan_id' => $planId,
            ],
            [
                'target' => $request->target,
                'target_status' => 'submitted',
                'target_submitted_at' => now(),
            ]
        );

        return back()->with('success', 'Target submitted successfully.');
    }

    /**
     * Approve a submitted target (supervisor action)
     */
    public function approveTarget($ipcrId)
    {
        $ipcr = IPCR::findOrFail($ipcrId);

        $ipcr->update([
            'target_status' => 'approved',
            'target_reviewed_at' => now(),
        ]);

        return back()->with('success', 'Target approved successfully.');
    }

    /**
     * Submit accomplishment and self-rating (employee action)
     */
    public function submitAccomplishment(Request $request, $ipcrId)
    {
        $request->validate([
            'accomplishment' => 'required|string|max:2000',
            'self_quality' => 'required|integer|min:1|max:5',
            'self_efficiency' => 'required|integer|min:1|max:5',
            'self_timeliness' => 'required|integer|min:1|max:5',
        ]);

        $ipcr = IPCR::findOrFail($ipcrId);

        $selfAverage = round(
            ($request->self_quality + $request->self_efficiency + $request->self_timeliness) / 3,
            2
        );

        $ipcr->update([
            'accomplishment' => $request->accomplishment,
            'self_quality' => $request->self_quality,
            'self_efficiency' => $request->self_efficiency,
            'self_timeliness' => $request->self_timeliness,
            'self_rating' => $selfAverage,
            'mov_link' => $request->mov_link,
            'accomplishment_submitted_at' => now(),
        ]);

        return back()->with('success', 'Accomplishment submitted successfully.');
    }

    /**
     * Review accomplishment and add supervisor rating (supervisor action)
     */
    public function reviewAccomplishment(Request $request, $ipcrId)
    {
        $request->validate([
            'sup_quality' => 'required|integer|min:1|max:5',
            'sup_efficiency' => 'required|integer|min:1|max:5',
            'sup_timeliness' => 'required|integer|min:1|max:5',
        ]);

        $ipcr = IPCR::findOrFail($ipcrId);

        $supAverage = round(
            ($request->sup_quality + $request->sup_efficiency + $request->sup_timeliness) / 3,
            2
        );

        $ipcr->update([
            'supervisor_quality' => $request->sup_quality,
            'supervisor_efficiency' => $request->sup_efficiency,
            'supervisor_timeliness' => $request->sup_timeliness,
            'supervisor_rating' => $supAverage,
            'accomplishment_reviewed_at' => now(),
        ]);

        return back()->with('success', 'Supervisor rating recorded successfully.');
    }
}
