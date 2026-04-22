<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SalarySchedule;
use App\Models\FacultyLoading\SstPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryScheduleController extends Controller
{
    // ── List all current salary schedule entries ──────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.approve');

        $grade = $request->integer('salary_grade', 0) ?: null;

        $rates = SalarySchedule::when($grade, fn ($q) => $q->where('salary_grade', $grade))
            ->orderBy('salary_grade')
            ->orderBy('step')
            ->get()
            ->map(fn ($r) => $this->mapRate($r));

        // Group by salary_grade for display
        $byGrade = $rates->groupBy('salary_grade')
            ->map(fn ($steps, $sg) => [
                'salary_grade'  => (int) $sg,
                'position_code' => $this->sgToPositionCode($sg),
                'steps'         => $steps->values(),
            ])
            ->values();

        $positions = SstPosition::active()->orderBy('salary_grade')
            ->get(['id', 'code', 'name', 'salary_grade']);

        $scheduleNames = SalarySchedule::select('schedule_name', 'effective_date', 'is_current')
            ->distinct()
            ->orderByDesc('effective_date')
            ->get();

        return Inertia::render('FacultyLoading/SalarySchedules/Index', [
            'byGrade'       => $byGrade,
            'positions'     => $positions,
            'scheduleNames' => $scheduleNames,
            'filters'       => ['salary_grade' => $grade],
        ]);
    }

    // ── JSON lookup for a given SG + step ─────────────────────────────────────

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'salary_grade' => 'required|integer|min:1|max:33',
            'step'         => 'nullable|integer|min:1|max:8',
        ]);

        $step        = $data['step'] ?? 1;
        $annualRate  = SalarySchedule::lookupAnnualRate($data['salary_grade'], $step);
        $allSteps    = SalarySchedule::ratesForGrade($data['salary_grade']);

        return response()->json([
            'salary_grade' => $data['salary_grade'],
            'step'         => $step,
            'annual_rate'  => $annualRate,
            'all_steps'    => $allSteps,
            'found'        => $annualRate !== null,
        ]);
    }

    // ── Create a salary schedule entry ────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'salary_grade'   => 'required|integer|min:1|max:33',
            'step'           => 'required|integer|min:1|max:8',
            'monthly_rate'   => 'required|numeric|min:1',
            'effective_date' => 'required|date',
            'is_current'     => 'boolean',
            'schedule_name'  => 'nullable|string|max:80',
            'remarks'        => 'nullable|string|max:500',
        ]);

        $data['annual_rate'] = round($data['monthly_rate'] * 12, 2);

        SalarySchedule::updateOrCreate(
            [
                'salary_grade'   => $data['salary_grade'],
                'step'           => $data['step'],
                'effective_date' => $data['effective_date'],
            ],
            $data
        );

        return back()->with('success', "SG {$data['salary_grade']} Step {$data['step']} rate saved.");
    }

    // ── Update a single rate entry ────────────────────────────────────────────

    public function update(Request $request, SalarySchedule $salarySchedule): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'monthly_rate'  => 'required|numeric|min:1',
            'is_current'    => 'boolean',
            'schedule_name' => 'nullable|string|max:80',
            'remarks'       => 'nullable|string|max:500',
        ]);

        $data['annual_rate'] = round($data['monthly_rate'] * 12, 2);

        $salarySchedule->update($data);

        return back()->with('success', 'Salary rate updated.');
    }

    // ── Activate a full schedule by name ──────────────────────────────────────

    public function activate(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.approve');

        $data = $request->validate([
            'schedule_name' => 'required|string|max:80',
        ]);

        $count = SalarySchedule::activateSchedule($data['schedule_name']);

        return back()->with('success', "Activated '{$data['schedule_name']}' ({$count} entries marked current).");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function mapRate(SalarySchedule $r): array
    {
        return [
            'id'             => $r->id,
            'salary_grade'   => $r->salary_grade,
            'step'           => $r->step,
            'monthly_rate'   => (float) $r->monthly_rate,
            'annual_rate'    => (float) $r->annual_rate,
            'effective_date' => $r->effective_date?->toDateString(),
            'is_current'     => $r->is_current,
            'schedule_name'  => $r->schedule_name,
        ];
    }

    private function sgToPositionCode(int $sg): ?string
    {
        return [12 => 'SST-I', 13 => 'SST-II', 14 => 'SST-III', 15 => 'SST-IV', 17 => 'SST-V'][$sg] ?? null;
    }
}
