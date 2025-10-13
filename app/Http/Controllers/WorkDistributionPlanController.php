<?php

namespace App\Http\Controllers;

use App\Models\WorkDistributionPlan;
use App\Models\PerformanceIndicator;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkDistributionPlanController extends Controller
{
    public function index()
    {
        return Inertia::render('PerformanceManagement/WorkDistributionPlans', [
            'plans' => WorkDistributionPlan::with(['performanceIndicator', 'personnel'])->latest()->get(),
            'indicators' => PerformanceIndicator::all(),
            'users' => User::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'performance_indicator_id' => 'required|exists:performance_indicators,id',
            'success_indicator' => 'required|string',
            'personnel_ids' => 'array',
            'personnel_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $plan = WorkDistributionPlan::create($validated);
        $plan->personnel()->sync($validated['personnel_ids'] ?? []);

        return redirect()->back();
    }

    public function update(Request $request, WorkDistributionPlan $workDistributionPlan)
    {
        $validated = $request->validate([
            'performance_indicator_id' => 'required|exists:performance_indicators,id',
            'success_indicator' => 'required|string',
            'personnel_ids' => 'array',
            'personnel_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $workDistributionPlan->update($validated);
        $workDistributionPlan->personnel()->sync($validated['personnel_ids'] ?? []);

        return redirect()->back();
    }

    public function destroy(WorkDistributionPlan $workDistributionPlan)
    {
        $workDistributionPlan->delete();
        return back()->with('success', 'WDP deleted successfully');
    }
}
