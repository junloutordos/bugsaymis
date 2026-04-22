<?php

namespace App\Http\Controllers;

use App\Models\SalaryGrade;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalaryGradeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('recruitment.view');

        // Group by tranche for multi-tranche support
        $tranches = SalaryGrade::select('tranche', 'effective_date', 'is_current')
            ->groupBy('tranche', 'effective_date', 'is_current')
            ->orderByDesc('effective_date')
            ->get();

        $selectedTranche = $request->input('tranche', $tranches->where('is_current', true)->first()?->tranche ?? 'SSL V T4 2023');

        // All rows for the selected tranche, shaped as grade => [step => rate]
        $rows = SalaryGrade::where('tranche', $selectedTranche)
            ->orderBy('grade')->orderBy('step')
            ->get(['id', 'grade', 'step', 'monthly_rate', 'tranche', 'effective_date', 'is_current']);

        return Inertia::render('SalaryGrades/Index', [
            'rows'            => $rows,
            'tranches'        => $tranches,
            'selectedTranche' => $selectedTranche,
            'grades'          => range(1, 33),
            'steps'           => range(1, 8),
        ]);
    }

    public function update(Request $request, SalaryGrade $salaryGrade)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'monthly_rate' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $salaryGrade->update($validated);

        return back()->with('success', "SG {$salaryGrade->grade} Step {$salaryGrade->step} updated.");
    }

    /**
     * Add a new tranche (copy current and mark old as not current).
     */
    public function storeTranche(Request $request)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'tranche'        => ['required', 'string', 'max:100', 'unique:salary_grades,tranche'],
            'effective_date' => ['required', 'date'],
            'base_tranche'   => ['required', 'string', 'exists:salary_grades,tranche'],
            'increment_pct'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $increment = 1 + (($validated['increment_pct'] ?? 0) / 100);

        // Copy from base tranche with optional increment
        $base = SalaryGrade::where('tranche', $validated['base_tranche'])->get();

        foreach ($base as $row) {
            SalaryGrade::create([
                'grade'          => $row->grade,
                'step'           => $row->step,
                'monthly_rate'   => round($row->monthly_rate * $increment, 2),
                'tranche'        => $validated['tranche'],
                'effective_date' => $validated['effective_date'],
                'is_current'     => false, // Admin manually sets active tranche
            ]);
        }

        return back()->with('success', "Tranche '{$validated['tranche']}' created from '{$validated['base_tranche']}'.");
    }

    /**
     * Set a tranche as the active (current) salary schedule.
     */
    public function setCurrentTranche(Request $request)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'tranche' => ['required', 'string', 'exists:salary_grades,tranche'],
        ]);

        SalaryGrade::query()->update(['is_current' => false]);
        SalaryGrade::where('tranche', $validated['tranche'])->update(['is_current' => true]);

        return back()->with('success', "Tranche '{$validated['tranche']}' is now the active salary schedule.");
    }
}
