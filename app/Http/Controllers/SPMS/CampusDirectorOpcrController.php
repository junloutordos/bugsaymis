<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Opcr;
use App\Models\SPMS\OpcrTarget;
use App\Models\SPMS\PerformanceIndicator;
use App\Services\SPMS\OPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CampusDirectorOpcrController extends Controller
{
    public function __construct(private readonly OPCRWorkflowService $workflow) {}

    public function index(): Response
    {
        $opcrs = Opcr::with('fiscalPeriod')
            ->where('ratee_user_id', Auth::id())
            ->latest()
            ->get();

        return Inertia::render('SPMS/CampusDirectorOpcrIndex', ['opcrs' => $opcrs]);
    }

    public function store(): RedirectResponse
    {
        $period = FiscalPeriod::current()->ofCadence('annual')->first();
        if (!$period) {
            return back()->withErrors(['fiscal_period' => 'No current annual fiscal period is configured. Ask an SPMS Admin to set one up.']);
        }

        $existing = Opcr::where('fiscal_period_id', $period->id)->first();
        if ($existing) {
            return redirect()->route('spms.opcr.show', $existing->id);
        }

        $opcr = Opcr::create(['fiscal_period_id' => $period->id, 'ratee_user_id' => Auth::id()]);

        return redirect()->route('spms.opcr.show', $opcr->id)->with('success', 'OPCR created.');
    }

    public function show(Opcr $opcr): Response
    {
        $this->authorizeRatee($opcr);

        return Inertia::render('SPMS/CampusDirectorOpcrShow', [
            'opcr' => $opcr->load(['fiscalPeriod', 'targets.performanceIndicator']),
        ]);
    }

    public function generateTargets(Opcr $opcr): RedirectResponse
    {
        $this->authorizeRatee($opcr);

        $indicatorIds = PerformanceIndicator::where('fiscal_year', $opcr->fiscalPeriod->fiscal_year)->pluck('id');

        $created = 0;
        foreach ($indicatorIds as $indicatorId) {
            $target = OpcrTarget::firstOrCreate([
                'opcr_id' => $opcr->id,
                'spms_performance_indicator_id' => $indicatorId,
            ]);
            if ($target->wasRecentlyCreated) {
                $created++;
            }
        }

        return back()->with('success', "Generated {$created} target(s) from campus-wide performance indicators.");
    }

    public function updateTargets(Opcr $opcr, Request $request): RedirectResponse
    {
        $this->authorizeRatee($opcr);

        $validated = $request->validate([
            'targets' => ['required', 'array'],
            'targets.*.q1_actual' => ['nullable', 'numeric'],
            'targets.*.q2_actual' => ['nullable', 'numeric'],
            'targets.*.q3_actual' => ['nullable', 'numeric'],
            'targets.*.q4_actual' => ['nullable', 'numeric'],
            'targets.*.remarks' => ['nullable', 'string'],
        ]);

        foreach ($validated['targets'] as $targetId => $fields) {
            $opcr->targets()->whereKey($targetId)->update($fields);
        }

        return back()->with('success', 'Accomplishments updated.');
    }

    public function submitToExecutiveDirector(Opcr $opcr): RedirectResponse
    {
        $this->authorizeRatee($opcr);

        $this->workflow->submitToExecutiveDirector($opcr, Auth::user());

        return back()->with('success', 'OPCR submitted to Executive Director.');
    }

    public function setOverride(Opcr $opcr, Request $request): RedirectResponse
    {
        $this->authorizeRatee($opcr);

        $validated = $request->validate([
            'override_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'override_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->workflow->setOverride($opcr, Auth::user(), $validated['override_rating'], $validated['override_reason']);

        return back()->with('success', 'Override rating recorded.');
    }

    private function authorizeRatee(Opcr $opcr): void
    {
        abort_unless($opcr->ratee_user_id === Auth::id() || Auth::user()->isSuperAdmin(), 403);
    }
}
