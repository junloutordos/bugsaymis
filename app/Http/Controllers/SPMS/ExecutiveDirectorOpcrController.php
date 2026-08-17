<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\Opcr;
use App\Services\SPMS\OPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ExecutiveDirectorOpcrController extends Controller
{
    public function __construct(private readonly OPCRWorkflowService $workflow) {}

    public function index(): Response
    {
        $opcrs = Opcr::with(['fiscalPeriod', 'ratee'])
            ->where('status', Opcr::STATUS_SUBMITTED_TO_ED)
            ->latest()
            ->get();

        return Inertia::render('SPMS/ExecutiveDirectorOpcrIndex', ['opcrs' => $opcrs]);
    }

    public function show(Opcr $opcr): Response
    {
        return Inertia::render('SPMS/ExecutiveDirectorOpcrShow', [
            'opcr' => $opcr->load(['fiscalPeriod', 'ratee', 'targets.performanceIndicator']),
        ]);
    }

    public function approve(Opcr $opcr): RedirectResponse
    {
        $this->workflow->approve($opcr, Auth::user());

        return back()->with('success', 'OPCR approved.');
    }

    public function setOverride(Opcr $opcr, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'override_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'override_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->workflow->setOverride($opcr, Auth::user(), $validated['override_rating'], $validated['override_reason']);

        return back()->with('success', 'Override rating recorded.');
    }

    public function returnToSender(Opcr $opcr, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->workflow->returnToSender($opcr, Auth::user(), $validated['reason']);

        return back()->with('success', 'Returned to Campus Director.');
    }
}
