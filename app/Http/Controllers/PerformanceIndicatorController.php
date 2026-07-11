<?php

namespace App\Http\Controllers;

use App\Models\PerformanceIndicator;
use App\Models\AgencyOutcome;
use App\Models\Division;
use App\Models\IPCRRatingPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PerformanceIndicatorController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        return Inertia::render('PerformanceManagement/PerformanceIndicators', [
            'indicators' => PerformanceIndicator::with(['agencyOutcome', 'divisions'])
                ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
                ->latest()->get(),
            'outcomes' => AgencyOutcome::query()
                ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
                ->get(),
            'divisions' => Division::all(),
            'fiscalYears'        => IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
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
        'fiscal_year' => 'nullable|integer|min:2000|max:2100',
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
        'fiscal_year' => 'nullable|integer|min:2000|max:2100',
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
