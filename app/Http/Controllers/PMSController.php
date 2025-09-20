<?php

namespace App\Http\Controllers;

use App\Models\PMS;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PMSController extends Controller
{
    public function index()
    {
        $pmsSchedules = PMS::with(['performedBy', 'equipments', 'dates'])->get();
        $users = User::all();
        $equipments = \App\Models\ICTEquipment::all();

        return Inertia::render('ITJobRequests/PMS', [
            'pmsSchedules' => $pmsSchedules,
            'users'        => $users,
            'equipments'   => $equipments,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'frequency'              => 'required|string|max:255',
            'school_year'            => 'required|string|max:20',   // ✅ new
            'office_area'            => 'required|string|max:255',  // ✅ new
            'status'                 => 'required|string|max:255',
            'remarks'                => 'nullable|string',
            'schedule_dates'         => 'required|array',
            'schedule_dates.*.date'  => 'required|date',
        ]);

        // Create PMS
        $pms = PMS::create([
            'title'        => $data['title'],
            'frequency'    => $data['frequency'],
            'school_year'  => $data['school_year'], // ✅
            'office_area'  => $data['office_area'],      // ✅
            'status'       => $data['status'],
            'remarks'      => $data['remarks'] ?? null,
            'performed_by' => Auth::id(),
        ]);

        // Store multiple schedule dates
        foreach ($data['schedule_dates'] as $date) {
            $pms->dates()->create([
                'schedule_date' => $date['date'],
            ]);
        }

        return redirect()->back()->with('success', 'PMS Schedule added successfully.');
    }

    public function update(Request $request, PMS $pms)
    {
        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'frequency'              => 'required|string|max:255',
            'school_year'            => 'required|string|max:20',   // ✅ new
            'office_area'            => 'required|string|max:255',  // ✅ new
            'status'                 => 'required|string|max:255',
            'remarks'                => 'nullable|string',
            'schedule_dates'         => 'required|array',
            'schedule_dates.*.date'  => 'required|date',
        ]);

        // Update PMS
        $pms->update([
            'title'        => $data['title'],
            'frequency'    => $data['frequency'],
            'school_year'  => $data['school_year'], // ✅
            'office_area'  => $data['office_area'],      // ✅
            'status'       => $data['status'],
            'remarks'      => $data['remarks'] ?? null,
            'performed_by' => Auth::id(),
        ]);

        // Replace old dates with new ones
        $pms->dates()->delete();
        foreach ($data['schedule_dates'] as $date) {
            $pms->dates()->create([
                'schedule_date' => $date['date'],
            ]);
        }

        return redirect()->back()->with('success', 'PMS Schedule updated successfully.');
    }

    public function destroy(PMS $pms)
    {
        $pms->delete();
        return redirect()->back()->with('success', 'PMS Schedule deleted successfully.');
    }

    public function assignEquipments(Request $request, $pmsId)
    {
        $data = $request->validate([
            'equipment_ids'   => 'required|array',
            'equipment_ids.*' => 'exists:ict_equipments,id',
        ]);

        $pms = PMS::findOrFail($pmsId);
        $pms->equipments()->syncWithoutDetaching($data['equipment_ids']);

        return redirect()->back()->with('success', 'Equipments assigned to PMS successfully.');
    }

    public function showEquipments(PMS $pms)
    {
        $pms->load(['equipments.histories', 'dates']); 
        // make sure ICTEquipment has `histories()` relation to ICTPMSHistory

        // keep schedule dates
        $pms->schedule_dates = $pms->dates->map(fn($d) => [
            'schedule_date' => $d->schedule_date,
            'status'        => $d->status,
        ]);

        // attach histories per equipment
        $equipments = $pms->equipments->map(function ($eq) {
            $eq->history_dates = $eq->histories->pluck('pms_date');
            return $eq;
        });

        return Inertia::render('ITJobRequests/PMSEquipments', [
            'pms'        => $pms,
            'equipments' => $equipments,
        ]);
    }



}
