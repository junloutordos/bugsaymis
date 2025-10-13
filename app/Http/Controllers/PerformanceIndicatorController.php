<?php

namespace App\Http\Controllers;

use App\Models\PerformanceIndicator;
use App\Models\AgencyOutcome;
use App\Models\Division;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PerformanceIndicatorController extends Controller
{
    public function index()
    {
        return Inertia::render('PerformanceManagement/PerformanceIndicators', [
            'indicators' => PerformanceIndicator::with(['agencyOutcome', 'divisions'])->latest()->get(),
            'outcomes' => AgencyOutcome::all(),
            'divisions' => Division::all(),
        ]);
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'description' => 'required|string',
        'target' => 'required|string',
        'agency_outcome_id' => 'required|exists:agency_org_outcomes,id',
        'division_ids' => 'array',
        'division_ids.*' => 'exists:divisions,id',
        'budget' => 'nullable|numeric',
    ]);

    $indicator = PerformanceIndicator::create($validated);
    $indicator->divisions()->sync($validated['division_ids'] ?? []);

    return redirect()->back();
}

public function update(Request $request, PerformanceIndicator $performanceIndicator)
{
    $validated = $request->validate([
        'description' => 'required|string',
        'target' => 'required|string',
        'agency_outcome_id' => 'required|exists:agency_org_outcomes,id',
        'division_ids' => 'array',
        'division_ids.*' => 'exists:divisions,id',
        'budget' => 'nullable|numeric',
    ]);

    $performanceIndicator->update($validated);
    $performanceIndicator->divisions()->sync($validated['division_ids'] ?? []);

    return redirect()->back();
}




    public function destroy(PerformanceIndicator $performanceIndicator)
    {
        $performanceIndicator->delete();
        return back()->with('success', 'Performance Indicator deleted successfully');
    }
}
