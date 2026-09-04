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
            ->topLevel()
            ->with('children')
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
            'outcome' => 'required_without:parent_id|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
            'function_type' => 'required_without:parent_id|string|max:255',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
            'parent_id' => 'nullable|exists:agency_org_outcomes,id',
        ]);

        $data = $this->inheritFromParentIfPresent($data);

        $outcome = AgencyOutcome::create($data);

        return redirect()->back()->with('outcome', $outcome);
    }

    public function update(Request $request, $id)
    {
        $agencyOutcome = AgencyOutcome::findOrFail($id);

        $data = $request->validate([
            'outcome' => 'required_without:parent_id|string|max:255',
            'sub_outcome' => 'nullable|string|max:255',
            'function_type' => 'required_without:parent_id|string|max:255',
            'fiscal_year' => 'nullable|integer|min:2000|max:2100',
            'parent_id' => 'nullable|exists:agency_org_outcomes,id',
        ]);

        $data = $this->inheritFromParentIfPresent($data);

        $agencyOutcome->update($data);

        return redirect()->back()->with('outcome', $agencyOutcome);
    }

    public function destroy($id)
    {
        $agencyOutcome = AgencyOutcome::findOrFail($id);

        if ($agencyOutcome->children()->exists()) {
            return back()->withErrors(['agencyOutcome' => 'Delete its sub-outcomes first before deleting this outcome.']);
        }

        if ($agencyOutcome->performanceIndicators()->exists()) {
            return back()->withErrors(['agencyOutcome' => 'This outcome is still referenced by one or more performance indicators.']);
        }

        if ($agencyOutcome->opcrIndicators()->exists()) {
            return back()->withErrors(['agencyOutcome' => 'This outcome is still tagged on one or more OPCR indicators.']);
        }

        $agencyOutcome->delete();

        return redirect()->back();
    }

    private function inheritFromParentIfPresent(array $data): array
    {
        if (empty($data['parent_id'])) {
            return $data;
        }

        $parent = AgencyOutcome::findOrFail($data['parent_id']);
        $data['outcome'] = $parent->outcome;
        $data['function_type'] = $parent->function_type;
        $data['fiscal_year'] = $parent->fiscal_year;

        return $data;
    }
}
