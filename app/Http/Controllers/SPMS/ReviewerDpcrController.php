<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\Dpcr;
use App\Services\SPMS\DPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReviewerDpcrController extends Controller
{
    private const PENDING_STATUSES = [
        Dpcr::STATUS_SUBMITTED_TO_REVIEWER,
        Dpcr::STATUS_REVIEWED,
        Dpcr::STATUS_SUBMITTED_TO_APPROVER,
    ];

    public function __construct(private readonly DPCRWorkflowService $workflow) {}

    public function index(): Response
    {
        $dpcrs = Dpcr::with(['division', 'fiscalPeriod', 'ratee'])
            ->whereIn('status', self::PENDING_STATUSES)
            ->latest()
            ->get();

        return Inertia::render('SPMS/ReviewerDpcrIndex', ['dpcrs' => $dpcrs]);
    }

    public function show(Dpcr $dpcr): Response
    {
        return Inertia::render('SPMS/ReviewerDpcrShow', [
            'dpcr' => $dpcr->load(['division', 'fiscalPeriod', 'ratee', 'targets.performanceIndicator']),
        ]);
    }

    public function review(Dpcr $dpcr): RedirectResponse
    {
        $this->workflow->review($dpcr, Auth::user());

        return back()->with('success', 'DPCR reviewed; rollup rating computed.');
    }

    public function submitToApprover(Dpcr $dpcr): RedirectResponse
    {
        $this->workflow->submitToApprover($dpcr, Auth::user());

        return back()->with('success', 'DPCR submitted to approver.');
    }

    public function approve(Dpcr $dpcr): RedirectResponse
    {
        $this->workflow->approve($dpcr, Auth::user());

        return back()->with('success', 'DPCR approved.');
    }

    public function setOverride(Dpcr $dpcr, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'override_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'override_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->workflow->setOverride($dpcr, Auth::user(), $validated['override_rating'], $validated['override_reason']);

        return back()->with('success', 'Override rating recorded.');
    }

    public function returnToSender(Dpcr $dpcr, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->workflow->returnToSender($dpcr, Auth::user(), $validated['reason']);

        return back()->with('success', 'Returned to Division Chief.');
    }
}
