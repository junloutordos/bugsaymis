<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HRIpcrV2Controller extends Controller
{
    public function __construct(
        private IpcrV2WorkflowService $workflow,
        private \App\Services\IPCRV2\StrategicFunctionService $strategic = new \App\Services\IPCRV2\StrategicFunctionService(),
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService()
    ) {}

    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')->latest('id')->get();

        return Inertia::render('IPCRV2/HRIpcrV2Index', ['records' => $records]);
    }

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions', 'statusLogs.actor'])->findOrFail($id);
        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/HRIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
        ]);
    }

    public function submitToPMT(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->transition(
            $record,
            IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
            actor: $request->user(),
            actionType: 'submitted',
        );

        return back()->with('success', 'Submitted to PMT.');
    }

    public function batchSubmitToPMT(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:ipcr_v2_records,id']);

        foreach (IpcrV2Record::whereIn('id', $data['ids'])->get() as $record) {
            if ($record->status === IpcrV2WorkflowService::STATUS_SUBMITTED_HR) {
                $this->workflow->transition(
                    $record,
                    IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                    actor: $request->user(),
                    actionType: 'submitted',
                );
            }
        }

        return back()->with('success', 'Batch submitted to PMT.');
    }
}
