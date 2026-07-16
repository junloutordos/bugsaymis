<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassScheduleApprovalBatch;
use App\Services\FacultyLoading\ClassScheduleApprovalService;
use Illuminate\Http\Request;

class ClassScheduleApprovalController extends Controller
{
    public function __construct(private readonly ClassScheduleApprovalService $service) {}

    public function submit(Request $request, AcademicTerm $term)
    {
        abort_unless($request->user()->hasRole('CID Chief') || $request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['pin' => 'required|string']);
        $this->service->submit($term, $request->user(), $data['pin']);

        return back()->with('success', 'The complete class schedule was submitted to OCD for approval.');
    }

    public function approve(Request $request, ClassScheduleApprovalBatch $batch)
    {
        abort_unless($request->user()->hasRole('OCD') || $request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['pin' => 'required|string']);
        $this->service->approve($batch, $request->user(), $data['pin']);

        return back()->with('success', 'The class schedule was approved and digitally signed.');
    }

    public function returnForRevision(Request $request, ClassScheduleApprovalBatch $batch)
    {
        abort_unless($request->user()->hasRole('OCD') || $request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $this->service->returnForRevision($batch, $request->user(), $data['reason']);

        return back()->with('success', 'The class schedule was returned to CID for revision.');
    }

    public function conforme(Request $request, ClassScheduleApprovalBatch $batch)
    {
        $data = $request->validate(['pin' => 'required|string']);
        $this->service->conforme($batch, $request->user(), $data['pin']);

        return back()->with('success', 'Your individual faculty schedule was signed Conforme.');
    }
}
