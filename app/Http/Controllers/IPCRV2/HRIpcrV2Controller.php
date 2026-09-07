<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HRIpcrV2Controller extends Controller
{
    public function __construct(private IpcrV2WorkflowService $workflow) {}

    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')->latest('id')->get();

        return Inertia::render('IPCRV2/HRIpcrV2Index', ['records' => $records]);
    }

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions'])->findOrFail($id);

        return Inertia::render('IPCRV2/HRIpcrV2Show', ['ipcr' => $record]);
    }

    public function submitToPMT(int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_SUBMITTED_PMT);

        return back()->with('success', 'Submitted to PMT.');
    }

    public function batchSubmitToPMT(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:ipcr_v2_records,id']);

        foreach (IpcrV2Record::whereIn('id', $data['ids'])->get() as $record) {
            if ($record->status === IpcrV2WorkflowService::STATUS_SUBMITTED_HR) {
                $this->workflow->transition($record, IpcrV2WorkflowService::STATUS_SUBMITTED_PMT);
            }
        }

        return back()->with('success', 'Batch submitted to PMT.');
    }
}
