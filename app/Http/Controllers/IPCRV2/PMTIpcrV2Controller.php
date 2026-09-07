<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\DigitalSignatureService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PMTIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private \App\Services\IPCRV2\StrategicFunctionService $strategic = new \App\Services\IPCRV2\StrategicFunctionService(),
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService(),
        private DigitalSignatureService $sigService = new DigitalSignatureService()
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

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'statusLogs.actor'])->findOrFail($id);
        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/PMTIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
            'isMutable' => $record->isMutable(),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);

        $data = $request->validate(['pin' => 'nullable|string']);
        $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_PMT_APPROVED,
            actor: $request->user(),
            actionType: 'approved',
            signedViaPin: ! empty($request->user()->signature_pin),
        );

        return back()->with('success', 'Approved by PMT.');
    }

    public function returnForRevision(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);

        $data = $request->validate(['remarks' => 'required|string|max:1000']);

        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_PMT_RETURNED,
            actor: $request->user(),
            remarks: $data['remarks'],
            actionType: 'returned',
        );

        return back()->with('success', 'Returned for revision.');
    }

    public function directorSign(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $data = $request->validate(['pin' => 'nullable|string']);

        $this->workflow->finalize($record, $request->user(), $data['pin'] ?? null);

        return back()->with('success', 'Director signed.');
    }
}
