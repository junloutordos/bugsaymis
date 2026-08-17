<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\Dpcr;
use App\Models\SPMS\DpcrTarget;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\PerformanceIndicator;
use App\Services\SPMS\DPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DivisionChiefDpcrController extends Controller
{
    public function __construct(private readonly DPCRWorkflowService $workflow) {}

    public function index(): Response
    {
        $dpcrs = Dpcr::with(['division', 'fiscalPeriod'])
            ->where('ratee_user_id', Auth::id())
            ->latest()
            ->get();

        return Inertia::render('SPMS/DivisionChiefDpcrIndex', ['dpcrs' => $dpcrs]);
    }

    public function store(): RedirectResponse
    {
        $divisionId = Auth::user()->division_id;
        if (!$divisionId) {
            return back()->withErrors(['division' => 'Your account has no division assigned. Contact HR.']);
        }

        $period = FiscalPeriod::current()->ofCadence('semester')->first();
        if (!$period) {
            return back()->withErrors(['fiscal_period' => 'No current semester fiscal period is configured. Ask an SPMS Admin to set one up.']);
        }

        $existing = Dpcr::where('division_id', $divisionId)->where('fiscal_period_id', $period->id)->first();
        if ($existing) {
            return redirect()->route('spms.dpcr.show', $existing->id);
        }

        $dpcr = Dpcr::create([
            'division_id' => $divisionId,
            'fiscal_period_id' => $period->id,
            'ratee_user_id' => Auth::id(),
        ]);

        return redirect()->route('spms.dpcr.show', $dpcr->id)->with('success', 'DPCR created.');
    }

    public function show(Dpcr $dpcr): Response
    {
        $this->authorizeRatee($dpcr);

        return Inertia::render('SPMS/DivisionChiefDpcrShow', [
            'dpcr' => $dpcr->load(['division', 'fiscalPeriod', 'targets.performanceIndicator']),
        ]);
    }

    public function generateTargets(Dpcr $dpcr): RedirectResponse
    {
        $this->authorizeRatee($dpcr);

        $indicatorIds = PerformanceIndicator::whereHas(
            'divisions',
            fn ($query) => $query->where('divisions.id', $dpcr->division_id)
        )->pluck('id');

        $created = 0;
        foreach ($indicatorIds as $indicatorId) {
            $target = DpcrTarget::firstOrCreate([
                'dpcr_id' => $dpcr->id,
                'spms_performance_indicator_id' => $indicatorId,
            ]);
            if ($target->wasRecentlyCreated) {
                $created++;
            }
        }

        return back()->with('success', "Generated {$created} target(s) from division performance indicators.");
    }

    public function updateTargets(Dpcr $dpcr, Request $request): RedirectResponse
    {
        $this->authorizeRatee($dpcr);

        $validated = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.q1_actual' => ['nullable', 'numeric'],
            'targets.*.q2_actual' => ['nullable', 'numeric'],
            'targets.*.q3_actual' => ['nullable', 'numeric'],
            'targets.*.q4_actual' => ['nullable', 'numeric'],
            'targets.*.remarks' => ['nullable', 'string'],
        ]);

        foreach ($validated['targets'] as $targetId => $fields) {
            $dpcr->targets()->whereKey($targetId)->update($fields);
        }

        return back()->with('success', 'Accomplishments updated.');
    }

    public function submitToReviewer(Dpcr $dpcr): RedirectResponse
    {
        $this->authorizeRatee($dpcr);

        $this->workflow->submitToReviewer($dpcr, Auth::user());

        return back()->with('success', 'DPCR submitted to reviewer.');
    }

    public function setOverride(Dpcr $dpcr, Request $request): RedirectResponse
    {
        $this->authorizeRatee($dpcr);

        $validated = $request->validate([
            'override_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'override_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->workflow->setOverride($dpcr, Auth::user(), $validated['override_rating'], $validated['override_reason']);

        return back()->with('success', 'Override rating recorded.');
    }

    private function authorizeRatee(Dpcr $dpcr): void
    {
        abort_unless($dpcr->ratee_user_id === Auth::id() || Auth::user()->isSuperAdmin(), 403);
    }
}
