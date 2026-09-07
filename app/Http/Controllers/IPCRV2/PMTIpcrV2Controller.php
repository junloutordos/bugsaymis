<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PMTIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private \App\Services\IPCRV2\StrategicFunctionService $strategic = new \App\Services\IPCRV2\StrategicFunctionService(),
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService()
    ) {}

    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')
            ->whereIn('status', [
                IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                IpcrV2WorkflowService::STATUS_PMT_APPROVED,
                IpcrV2WorkflowService::STATUS_PMT_RETURNED,
            ])
            ->latest('id')
            ->get();

        return Inertia::render('IPCRV2/PMTIpcrV2Index', ['records' => $records]);
    }

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period'])->findOrFail($id);
        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/PMTIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
            'isMutable' => $record->isMutable(),
        ]);
    }

    public function approve(int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_PMT_APPROVED);

        return back()->with('success', 'Approved by PMT.');
    }

    public function returnForRevision(int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_PMT_RETURNED);

        return back()->with('success', 'Returned for revision.');
    }

    public function directorSign(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->finalize($record, $request->user());

        return back()->with('success', 'Director signed.');
    }
}
