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
            'office_involved' => 'nullable|string',
            'rated_by' => 'nullable|string',
        ]);


        $plan = WorkDistributionPlan::create($validated);
        $plan->personnel()->sync($validated['personnel_ids'] ?? []);

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $workDistributionPlan = WorkDistributionPlan::findOrFail($id);

        $validated = $request->validate([
            'performance_indicator_id' => 'required|exists:performance_indicators,id',
            'success_indicator' => 'required|string',
            'office_involved' => 'nullable|string',
            'rated_by' => 'nullable|string',
        ]);

        $workDistributionPlan->update($validated);

        return redirect()->back();
    }


    public function destroy(WorkDistributionPlan $workDistributionPlan)
    {
        $workDistributionPlan->delete();
        return back()->with('success', 'WDP deleted successfully');
    }
}
