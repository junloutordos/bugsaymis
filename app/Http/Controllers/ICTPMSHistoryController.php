<?php

namespace App\Http\Controllers;

use App\Models\ICTPMSHistory;
use App\Models\PMS;
use Illuminate\Http\Request;

class ICTPMSHistoryController extends Controller
{
    /**
     * Store a newly created PMS history record.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'ict_pms_id'     => 'required|integer|exists:ict_pms,id',
            'equipment_id'   => 'required|integer|exists:ict_equipments,id',
            'pms_date'       => 'nullable|date',
            'description'    => 'nullable|string',
            'type'           => 'required|string|max:50',
            'cost_of_repair' => 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string',
        ]);

        $data['created_by'] = auth()->id();

        // Save PMS history
        ICTPMSHistory::create($data);

        // ✅ Update status in ict_pms_dates instead of ict_pms_equipment
        if (!empty($data['pms_date'])) {
            $pms = PMS::findOrFail($data['ict_pms_id']);

            $pms->dates()
                ->whereDate('schedule_date', $data['pms_date'])
                ->update(['status' => 'done']);
        }

        return redirect()->back()->with('success', 'PMS history saved and schedule status updated successfully.');
    }
}

