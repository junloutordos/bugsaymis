<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\FiscalPeriod;
use App\Models\SPMS\Ipcr;
use App\Services\SPMS\IPCRTargetGenerationService;
use App\Services\SPMS\IPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeIpcrController extends Controller
{
    public function __construct(
        private readonly IPCRWorkflowService $workflow,
        private readonly IPCRTargetGenerationService $targetGeneration,
    ) {}

    public function index(): Response
    {
        $ipcrs = Ipcr::with('fiscalPeriod')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return Inertia::render('SPMS/EmployeeIpcrIndex', ['ipcrs' => $ipcrs]);
    }

    public function store(): RedirectResponse
    {
        $period = FiscalPeriod::current()->ofCadence('semester')->first();
        if (!$period) {
            return back()->withErrors(['fiscal_period' => 'No current semester fiscal period is configured. Ask an SPMS Admin to set one up.']);
        }

        $existing = Ipcr::where('user_id', Auth::id())->where('fiscal_period_id', $period->id)->first();
        if ($existing) {
            return redirect()->route('spms.ipcr.show', $existing->id);
        }

        $ipcr = Ipcr::create(['user_id' => Auth::id(), 'fiscal_period_id' => $period->id]);

        return redirect()->route('spms.ipcr.show', $ipcr->id)->with('success', 'IPCR created.');
    }

    public function show(Ipcr $ipcr): Response
    {
        $this->authorizeOwner($ipcr);

        return Inertia::render('SPMS/EmployeeIpcrShow', [
            'ipcr' => $ipcr->load(['fiscalPeriod', 'targets.movChecklistItems']),
        ]);
    }

    public function generateTargets(Ipcr $ipcr): RedirectResponse
    {
        $this->authorizeOwner($ipcr);

        $result = $this->targetGeneration->generate($ipcr);

        return back()->with('success', "Generated {$result['attached']} target(s) from your current load assignments.");
    }

    public function submitTarget(Ipcr $ipcr): RedirectResponse
    {
        $this->authorizeOwner($ipcr);

        $this->workflow->submitTarget($ipcr, Auth::user());

        return back()->with('success', 'Target submitted for approval.');
    }

    public function submitForRating(Ipcr $ipcr): RedirectResponse
    {
        $this->authorizeOwner($ipcr);

        $this->workflow->submitForRating($ipcr, Auth::user());

        return back()->with('success', 'IPCR submitted for rating.');
    }

    private function authorizeOwner(Ipcr $ipcr): void
    {
        abort_unless($ipcr->user_id === Auth::id() || Auth::user()->isSuperAdmin(), 403);
    }
}
