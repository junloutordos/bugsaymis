<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRRatingPeriod;
use App\Models\IPCRV2\IpcrV2CoreItem;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRV2\IpcrV2SupportItem;
use App\Services\IPCRV2\IpcrV2GenerationService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeIpcrV2Controller extends Controller
{
    private const EDITABLE_STATUSES = [
        IpcrV2WorkflowService::STATUS_NEW_TARGET,
        IpcrV2WorkflowService::STATUS_RETURNED,
    ];

    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private IpcrV2GenerationService $generation,
        private StrategicFunctionService $strategic
    ) {}

    public function index(Request $request)
    {
        $records = IpcrV2Record::where('user_id', $request->user()->id)
            ->with('period')
            ->latest('id')
            ->get();

        return Inertia::render('IPCRV2/EmployeeIpcrV2Index', [
            'records' => $records,
            'openPeriods' => IPCRRatingPeriod::open()->get(['id', 'label', 'year', 'semester']),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period'])->findOrFail($id);

        $isOwner = $record->user_id === $request->user()->id;
        abort_unless(
            $isOwner || $this->workflow->canManage($request->user(), $record),
            403,
            "You are not this employee's immediate supervisor and cannot view this IPCR V2."
        );

        return Inertia::render('IPCRV2/EmployeeIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'isOwner' => $isOwner,
            'isMutable' => $record->isMutable(),
        ]);
    }

    public function generateTargets(Request $request)
    {
        $data = $request->validate(['rating_period_id' => 'required|exists:ipcr_rating_periods,id']);
        $period = IPCRRatingPeriod::findOrFail($data['rating_period_id']);

        $record = $this->generation->generateTargets($request->user(), $period);

        return redirect()->route('employee-ipcr-v2.show', $record->id)->with('success', 'IPCR V2 targets generated.');
    }

    public function submitForReview(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_FOR_REVIEW);

        return back()->with('success', 'Submitted for review.');
    }

    public function submitForRating(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_FOR_RATING, ['submitted_for_rating_at' => now()]);

        return back()->with('success', 'Submitted for rating.');
    }

    public function updateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'target' => 'nullable|string|max:1000',
            'actual_accomplishment' => 'nullable|string|max:1000',
        ]);
        $coreItem->update($data);

        return back()->with('success', 'Updated.');
    }

    public function updateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_if($supportItem->ipcr_v2_id !== $record->id, 404);

        $data = $request->validate([
            'actual_accomplishment' => 'nullable|string|max:1000',
            'mov_link' => 'nullable|string|max:500',
        ]);
        $supportItem->update($data);

        return back()->with('success', 'Updated.');
    }

    public function destroy(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->assertOwner($request->user(), $record);
        $this->workflow->assertMutable($record);
        abort_unless(
            in_array($record->status, self::EDITABLE_STATUSES, true),
            403,
            'Only IPCR V2 records that are new or returned for revision can be deleted.'
        );

        $record->delete();

        return back()->with('success', 'IPCR V2 record deleted.');
    }
}
