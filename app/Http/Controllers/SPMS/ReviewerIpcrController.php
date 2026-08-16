<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\Ipcr;
use App\Services\SPMS\IPCRWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReviewerIpcrController extends Controller
{
    private const PENDING_STATUSES = [
        Ipcr::STATUS_TARGET_SUBMITTED,
        Ipcr::STATUS_SUBMITTED_FOR_RATING,
        Ipcr::STATUS_RATED,
        Ipcr::STATUS_DC_REVIEWED,
        Ipcr::STATUS_PMT_HR_REVIEWED,
    ];

    public function __construct(private readonly IPCRWorkflowService $workflow) {}

    public function index(): Response
    {
        $ipcrs = Ipcr::with(['user', 'fiscalPeriod'])
            ->whereIn('status', self::PENDING_STATUSES)
            ->latest()
            ->get();

        return Inertia::render('SPMS/ReviewerIpcrIndex', ['ipcrs' => $ipcrs]);
    }

    public function show(Ipcr $ipcr): Response
    {
        return Inertia::render('SPMS/ReviewerIpcrShow', [
            'ipcr' => $ipcr->load(['user', 'fiscalPeriod', 'targets.movChecklistItems']),
        ]);
    }

    public function approveTarget(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->approveTarget($ipcr, Auth::user());

        return back()->with('success', 'Target approved.');
    }

    public function rate(Ipcr $ipcr, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ratings' => ['required', 'array'],
            'ratings.*.rating_q' => ['required', 'numeric', 'min:1', 'max:5'],
            'ratings.*.rating_e' => ['required', 'numeric', 'min:1', 'max:5'],
            'ratings.*.rating_t' => ['required', 'numeric', 'min:1', 'max:5'],
        ]);

        foreach ($validated['ratings'] as $targetId => $scores) {
            $ipcr->targets()->whereKey($targetId)->update($scores);
        }

        $this->workflow->rate($ipcr, Auth::user());

        return back()->with('success', 'IPCR rated.');
    }

    public function reviewAsDivisionChief(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->reviewByDivisionChief($ipcr, Auth::user());

        return back()->with('success', 'Reviewed as Division Chief.');
    }

    public function reviewAsPmtHr(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->reviewByPmtHr($ipcr, Auth::user());

        return back()->with('success', 'Reviewed by PMT/HR.');
    }

    public function finalize(Ipcr $ipcr): RedirectResponse
    {
        $this->workflow->finalize($ipcr, Auth::user());

        return back()->with('success', 'IPCR signed and finalized.');
    }

    public function returnToSender(Ipcr $ipcr, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $this->workflow->returnToSender($ipcr, Auth::user(), $validated['reason']);

        return back()->with('success', 'Returned to employee.');
    }
}
