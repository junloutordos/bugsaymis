<?php

namespace App\Http\Controllers;

use App\Models\EmployeeIPCR;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DivisionChiefIPCRController extends Controller
{
    /**
     * Display all IPCRs of subordinates under the division chief's division.
     */
    public function index()
    {
        $user = auth()->user();

        if (!$user->division_id) {
            abort(403, "You are not authorized to view this.");
        }

        // Load all IPCRs of subordinates with their user info
        $ipcrs = EmployeeIPCR::with('user')
            ->whereHas('user', fn($q) => $q->where('division_id', $user->division_id))
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('PerformanceManagement/DivisionChiefIPCR', [
            'ipcrs' => $ipcrs
        ]);
    }

    /**
     * Show a single IPCR target with associated plans.
     */
    public function show($id)
    {
        $ipcr = EmployeeIPCR::with([
            'plans.performance_indicator.agencyOutcome'
        ])->findOrFail($id);

        return Inertia::render('PerformanceManagement/DivisionChiefIPCRShow', [
            'ipcr'   => $ipcr,
            'plans'  => $ipcr->plans,
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
        
        $employeeIPCR->save();

        return to_route('division-employee-ipcr.show', $employeeIPCR->id)
            ->with('success', 'Targets approved successfully.');
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
