<?php

namespace App\Http\Controllers;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgencyOutcomeController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = IPCRRatingPeriod::current()->value('year') ?? (int) now()->format('Y');
        $selectedFY  = $request->query('fiscal_year', (string) $currentYear);

        $outcomes = AgencyOutcome::query()
            ->when($selectedFY !== 'all', fn ($q) => $q->forFiscalYear((int) $selectedFY))
            ->latest()
            ->get();

        return Inertia::render('PerformanceManagement/AgencyOrgOutcome', [
            'outcomes'           => $outcomes,
            'fiscalYears'        => IPCRRatingPeriod::query()->distinct()->orderByDesc('year')->pluck('year'),
            'selectedFiscalYear' => $selectedFY,
            'currentFiscalYear'  => $currentYear,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'outcome' => 'required|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
            'function_type' => 'required|string|max:255',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $outcome = AgencyOutcome::create($data);

        return redirect()->back()->with('outcome', $outcome);
    }

    public function update(Request $request, AgencyOutcome $agencyOutcome)
    {
        $data = $request->validate([
            'outcome' => 'required|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
            'function_type' => 'required|string|max:255',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $agencyOutcome->update($data);

        return redirect()->back()->with('outcome', $agencyOutcome);
    }

    public function destroy(AgencyOutcome $agencyOutcome)
    {
        $agencyOutcome->delete();

        return redirect()->back();
    }
}
