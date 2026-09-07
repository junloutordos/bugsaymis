<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DivisionChiefIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private StrategicFunctionService $strategic,
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService()
    ) {}

    public function index(Request $request)
    {
        $records = IpcrV2Record::with('user', 'period')
            ->whereHas('user', function ($q) use ($request) {
                $q->where('division_id', $request->user()->division_id);
            })
            ->latest('id')
            ->get();

        return Inertia::render('IPCRV2/DivisionChiefIpcrV2Index', ['records' => $records]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions'])->findOrFail($id);

        abort_unless(
            $request->user()->hasRole('OCD') || $record->user?->division_id === $request->user()->division_id,
            403,
            'This employee is not in your division.'
        );

        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/DivisionChiefIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
            'isMutable' => $record->isMutable(),
        ]);
    }

    public function approveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_TARGETS_APPROVED, ['target_approved_at' => now()]);

        return back()->with('success', 'Targets approved.');
    }

    public function disapproveTargets(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_RETURNED);

        return back()->with('success', 'Returned for revision.');
    }

    public function rateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'student_feedback_rating' => 'required|integer|min:1|max:5',
            'supervisor_feedback_rating' => 'required|integer|min:1|max:5',
            'im_development_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $data['row_average'] = round(
            $data['student_feedback_rating'] * 0.30
            + $data['supervisor_feedback_rating'] * 0.20
            + $data['im_development_rating'] * 0.20
            + $data['timeliness_rating'] * 0.30,
            2
        );

        $coreItem->update($data);

        return back()->with('success', 'Rated.');
    }

    public function rateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanManage($request->user(), $record);
        abort_if($supportItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'quality_rating' => 'required|integer|min:1|max:5',
            'efficiency_rating' => 'required|integer|min:1|max:5',
            'timeliness_rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $data['row_average'] = round(($data['quality_rating'] + $data['efficiency_rating'] + $data['timeliness_rating']) / 3, 2);

        $supportItem->update($data);

        return back()->with('success', 'Rated.');
    }

    public function submitToPMT(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertCanEndorse($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_SUBMITTED_PMT, ['submitted_for_pmtreview_at' => now()]);

        return back()->with('success', 'Submitted to PMT.');
    }
}
