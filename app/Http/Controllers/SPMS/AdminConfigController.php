<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\WeightProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminConfigController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SPMS/AdminConfigIndex', [
            'weightProfiles' => WeightProfile::with('division')->latest()->get(),
            'fiscalPeriods' => FiscalPeriod::latest()->get(),
        ]);
    }

    public function storeWeightProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'in:opcr,dpcr,ipcr'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'fiscal_year' => ['required', 'integer', 'min:2000'],
            'strategic_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'core_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'support_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $sum = $validated['strategic_pct'] + $validated['core_pct'] + $validated['support_pct'];
        if (abs($sum - 100) > 0.01) {
            return back()->withErrors(['strategic_pct' => 'Strategic + Core + Support must sum to 100.']);
        }

        WeightProfile::create($validated);

        return back()->with('success', 'Weight profile saved.');
    }

    public function storeFiscalPeriod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cadence' => ['required', 'in:quarter,semester,annual'],
            'fiscal_year' => ['required', 'integer', 'min:2000'],
            'label' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'parent_period_id' => ['nullable', 'exists:spms_fiscal_periods,id'],
            'school_year_id' => ['nullable', 'exists:school_years,id'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            if (!empty($validated['is_current'])) {
                FiscalPeriod::where('cadence', $validated['cadence'])->update(['is_current' => false]);
            }
            FiscalPeriod::create($validated);
        });

        return back()->with('success', 'Fiscal period saved.');
    }
}
