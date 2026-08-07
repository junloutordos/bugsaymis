<?php

namespace App\Http\Controllers;

use App\Models\EmployeeIPCR;
use App\Models\IPCRCoachingSession;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use App\Services\PersonNameFormatter;
use Illuminate\Http\Request;

class IPCRCoachingSessionController extends Controller
{
    public function __construct(
        private IPCRWorkflowService $workflow,
        private PersonNameFormatter $names,
    ) {
    }

    public function store(Request $request, EmployeeIPCR $employeeIPCR)
    {
        $user = auth()->user();
        $this->workflow->assertCanManage($user, $employeeIPCR);

        $data = $request->validate([
            'activity_type'   => 'required|in:monitoring,coaching',
            'mechanism'       => 'required|in:one_on_one,group',
            'meeting_date'    => 'required|date',
            'channel_memo'    => 'boolean',
            'channel_others'  => 'nullable|string|max:255',
            'remarks'         => 'required|string|max:5000',
            'noted_by_name'   => 'nullable|string|max:255',
            'noted_at'        => 'nullable|date',
        ]);

        IPCRCoachingSession::create([
            'employee_ipcr_id'  => $employeeIPCR->id,
            'created_by'        => $user->id,
            'activity_type'     => $data['activity_type'],
            'mechanism'         => $data['mechanism'],
            'meeting_date'      => $data['meeting_date'],
            'channel_memo'      => $data['channel_memo'] ?? false,
            'channel_others'    => $data['channel_others'] ?? null,
            'remarks'           => $data['remarks'],
            'conducted_by_name' => $this->names->formal($user),
            'conducted_at'      => $data['meeting_date'],
            'noted_by_name'     => $data['noted_by_name'] ?? null,
            'noted_at'          => $data['noted_at'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Coaching/mentoring session logged.');
    }

    public function destroy(EmployeeIPCR $employeeIPCR, IPCRCoachingSession $coachingSession)
    {
        $user = auth()->user();
        $this->workflow->assertCanManage($user, $employeeIPCR);

        abort_unless($coachingSession->employee_ipcr_id === $employeeIPCR->id, 404);

        $coachingSession->delete();

        return redirect()->back()->with('success', 'Coaching/mentoring session removed.');
    }
}
