<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Models\IPCRV2\IpcrV2Record;
use Inertia\Inertia;

class AdminIpcrV2Controller extends Controller
{
    public function index()
    {
        $records = IpcrV2Record::with('user', 'period')->latest('id')->get();

        return Inertia::render('IPCRV2/AdminIpcrV2Index', ['records' => $records]);
    }

    public function show(int $id)
    {
        $record = IpcrV2Record::with(['user', 'coreItems', 'supportItems', 'period', 'coachingSessions'])->findOrFail($id);

        return Inertia::render('IPCRV2/HRIpcrV2Show', ['ipcr' => $record]);
    }
}
