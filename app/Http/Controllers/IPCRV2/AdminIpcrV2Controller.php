<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use Inertia\Inertia;

class AdminIpcrV2Controller extends Controller
{
    public function __construct(
        private \App\Services\IPCRV2\StrategicFunctionService $strategic = new \App\Services\IPCRV2\StrategicFunctionService(),
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService()
    ) {}

    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')->latest('id')->get();

        return Inertia::render('IPCRV2/AdminIpcrV2Index', ['records' => $records]);
    }

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions'])->findOrFail($id);
        $ocdUser = \App\Models\User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/AdminIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser?->only('name', 'position'),
            'summary' => $this->summaryService->buildRows($record),
        ]);
    }
}
