<?php

namespace App\Http\Controllers;

use App\Models\ComputerLabScheduleApproval;
use App\Models\FacultyLoading\AcademicTerm;
use App\Services\ComputerLabScheduleApprovalService;
use Illuminate\Http\Request;

class ComputerLabScheduleApprovalController extends Controller
{
    public function __construct(private readonly ComputerLabScheduleApprovalService $service) {}

    public function submit(Request $request, AcademicTerm $term)
    {
        abort_unless(
            (int) $request->user()->id === ComputerLabScheduleApprovalService::PREPARER_USER_ID
                || $request->user()->isSuperAdmin(),
            403,
        );

        $data = $request->validate(['pin' => 'required|string']);
        $this->service->submit($term, $request->user(), $data['pin']);

        return back()->with('success', 'The computer laboratory schedule was submitted to the CID Chief for approval.');
    }

    public function approve(Request $request, ComputerLabScheduleApproval $approval)
    {
        abort_unless($request->user()->hasRole('CID Chief') || $request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['pin' => 'required|string']);
        $this->service->approve($approval, $request->user(), $data['pin']);

        return back()->with('success', 'The computer laboratory schedule was approved and digitally signed.');
    }

    public function returnForRevision(Request $request, ComputerLabScheduleApproval $approval)
    {
        abort_unless($request->user()->hasRole('CID Chief') || $request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $this->service->returnForRevision($approval, $request->user(), $data['reason']);

        return back()->with('success', 'The computer laboratory schedule was returned for revision.');
    }
}
