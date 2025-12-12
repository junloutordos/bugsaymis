<?php

namespace App\Http\Controllers;

use App\Models\EmployeeIPCR;
use App\Models\WorkDistributionPlan;
use Illuminate\Http\Request;
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

        return Inertia::render('PerformanceManagement/EmployeeIPCR', [
            'ipcrs' => $ipcrs,
            'workPlans' => $workPlans,
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

        EmployeeIPCR::create([
            'user_id'       => auth()->id(),
            'rating_period' => $data['rating_period'],
            'title'         => $data['title'],
            'status'        => 'New Target',
            'remarks'       => $data['remarks'] ?? null,
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
            'user.division.divisionchief',   // Employee → Division → Division Chief
            'plans.performance_indicator.agencyOutcome'
        ])->findOrFail($id);

        return Inertia::render('PerformanceManagement/EmployeeIPCRShow', [
            'ipcr'       => $ipcr,
            'employee'   => $ipcr->user,                              // employee
            'supervisor' => $ipcr->user->division->divisionchief,     // division chief
            'plans'      => $ipcr->plans,
        ]);
    }


    /**
     * Update self ratings/accomplishment – CORRECTED VERSION
     * This now updates ONLY the pivot of THIS IPCR + THIS plan
     */
    public function updateSelfRating(Request $request, EmployeeIPCR $ipcr, $planId)
    {
        $request->validate([
            'accomplishment'  => 'nullable|string|max:255',
            'mov_link'        => 'nullable|url|max:255',
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


    public function submitForReview(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'For Review',
            'submitted_for_review_at' => now(),
        ]);

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted for review successfully.');
    }

    public function submitForRating(EmployeeIPCR $employeeIPCR)
    {
        $employeeIPCR->update([
            'status' => 'Submitted for Rating',
            'submitted_for_rating_at' => now(),
        ]);

        return to_route('employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'IPCR submitted for rating successfully.');
    }



}
