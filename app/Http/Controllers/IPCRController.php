<?php

namespace App\Http\Controllers;

use App\Models\IPCR;
use App\Models\WorkDistributionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IPCRController extends Controller
{
    /**
     * Display all assigned plans for the authenticated user.
     */
    public function index()
    {
        $user = auth()->user();

        $plans = WorkDistributionPlan::with([
            'ipcrs' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        ])
        ->whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->latest('created_at')
        ->get();

        return Inertia::render('PerformanceManagement/IPCR', [
            'plans' => $plans,
        ]);
    }

    /**
     * Submit or update IPCR target for a plan.
     */
    public function submitTarget(Request $request, $planId)
    {
        $request->validate(['target' => 'required|string|max:1000']);

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
     * Approve a submitted target (supervisor action).
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
     * Submit accomplishment and self-rating (Q, E, T).
     */
    // Submit accomplishment with self Q/E/T
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
        'accomplishment_submitted_at' => now(),
    ]);

    return back()->with('success', 'Accomplishment submitted successfully.');
}

// Supervisor review with Q/E/T
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
