<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2CoachingSession;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;

class IpcrV2CoachingSessionController extends Controller
{
    public function __construct(private IpcrV2WorkflowService $workflow) {}

    public function store(Request $request, int $ipcrV2)
    {
        $record = IpcrV2Record::findOrFail($ipcrV2);
        $this->workflow->assertCanManage($request->user(), $record);

        $data = $request->validate([
            'activity_type' => 'required|in:monitoring,coaching',
            'mechanism' => 'required|in:one_on_one,group',
            'meeting_date' => 'required|date',
            'channel_memo' => 'boolean',
            'channel_others' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $record->coachingSessions()->create(array_merge($data, [
            'conducted_by_name' => $request->user()->name,
            'conducted_at' => now(),
        ]));

        return back()->with('success', 'Coaching session logged.');
    }

    public function destroy(Request $request, int $ipcrV2, IpcrV2CoachingSession $coachingSession)
    {
        $record = IpcrV2Record::findOrFail($ipcrV2);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($coachingSession->ipcr_v2_id !== $record->id, 404);

        $coachingSession->delete();

        return back()->with('success', 'Coaching session removed.');
    }
}
