<?php

namespace App\Http\Controllers\Atlas;

use App\Http\Controllers\Controller;
use App\Models\Atlas\WorkspaceSyncProposal;
use App\Services\Atlas\WorkspaceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WorkspaceSyncController extends Controller
{
    public function __construct(
        private readonly WorkspaceSyncService $sync,
    ) {}

    public function index(Request $request)
    {
        $type = $request->query('type', 'student');

        $proposals = WorkspaceSyncProposal::query()
            ->where('subject_type', $type)
            ->where('status', 'pending')
            ->orderByRaw("FIELD(confidence, 'high', 'medium')")
            ->orderBy('subject_name')
            ->paginate(50)
            ->withQueryString();

        $counts = WorkspaceSyncProposal::query()
            ->where('status', 'pending')
            ->selectRaw('subject_type, count(*) as total')
            ->groupBy('subject_type')
            ->pluck('total', 'subject_type');

        return Inertia::render('Atlas/WorkspaceSync', [
            'proposals' => $proposals,
            'activeType' => $type,
            'counts' => [
                'student' => (int) ($counts['student'] ?? 0),
                'employee' => (int) ($counts['employee'] ?? 0),
            ],
        ]);
    }

    public function runScan(): RedirectResponse
    {
        $result = $this->sync->scan();

        return back()->with('success', sprintf(
            'Scan complete — %d Workspace accounts checked, %d student and %d employee proposals staged.',
            $result['scanned_workspace_users'],
            $result['students_proposed'],
            $result['employees_proposed'],
        ));
    }

    public function approve(WorkspaceSyncProposal $proposal): RedirectResponse
    {
        abort_if($proposal->status !== 'pending', 422, 'This proposal has already been reviewed.');

        $this->sync->approve($proposal, Auth::user());

        return back()->with('success', "Updated {$proposal->subject_name}'s official email.");
    }

    public function reject(WorkspaceSyncProposal $proposal): RedirectResponse
    {
        abort_if($proposal->status !== 'pending', 422, 'This proposal has already been reviewed.');

        $this->sync->reject($proposal, Auth::user());

        return back()->with('success', "Dismissed the proposal for {$proposal->subject_name}.");
    }
}
