<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhAppliance;
use App\Models\ResidenceHall\RhIntern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RhApplianceController extends Controller
{
    private function checkAccess(RhIntern $intern): void
    {
        $this->authorize('rh.interns.manage');
        $user = Auth::user();
        if (!$user->isSuperAdmin() && $user->rh_hall && $intern->room && $intern->room->residence_hall !== $user->rh_hall) {
            abort(403);
        }
    }

    public function store(Request $request, RhIntern $rhIntern)
    {
        $this->checkAccess($rhIntern);

        $data = $request->validate([
            'device_type' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:255',
            'unit_count'  => 'required|integer|min:1',
            'wattage'     => 'nullable|numeric|min:0',
            'fee_amount'  => 'required|numeric|min:0',
        ]);

        $data['rh_intern_id'] = $rhIntern->id;
        $data['is_approved']  = false;
        RhAppliance::create($data);

        return back()->with('success', 'Appliance added.');
    }

    public function update(Request $request, RhAppliance $rhAppliance)
    {
        $this->checkAccess($rhAppliance->intern);

        $data = $request->validate([
            'device_type' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:255',
            'unit_count'  => 'required|integer|min:1',
            'wattage'     => 'nullable|numeric|min:0',
            'fee_amount'  => 'required|numeric|min:0',
        ]);

        $rhAppliance->update($data);
        return back()->with('success', 'Appliance updated.');
    }

    public function destroy(RhAppliance $rhAppliance)
    {
        $this->checkAccess($rhAppliance->intern);
        $rhAppliance->delete();
        return back()->with('success', 'Appliance removed.');
    }

    public function approve(RhAppliance $rhAppliance)
    {
        $this->checkAccess($rhAppliance->intern);
        $rhAppliance->update([
            'is_approved' => true,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        return back()->with('success', 'Appliance approved.');
    }
}
