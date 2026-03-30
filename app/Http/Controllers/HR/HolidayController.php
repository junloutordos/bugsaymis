<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Holiday;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index()
    {
        $this->authorize('hr.dtr.manage');

        return Inertia::render('HR/Holidays/Index', [
            'holidays' => Holiday::orderBy('holiday_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('hr.dtr.manage');

        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'holiday_date' => 'required|date',
            'type'         => 'required|in:regular,special_non_working,special_working,local',
            'is_recurring' => 'boolean',
            'description'  => 'nullable|string|max:500',
            'is_active'    => 'boolean',
        ]);

        Holiday::create($data);

        return back()->with('success', 'Holiday added.');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $this->authorize('hr.dtr.manage');

        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'holiday_date' => 'required|date',
            'type'         => 'required|in:regular,special_non_working,special_working,local',
            'is_recurring' => 'boolean',
            'description'  => 'nullable|string|max:500',
            'is_active'    => 'boolean',
        ]);

        $holiday->update($data);

        return back()->with('success', 'Holiday updated.');
    }

    public function destroy(Holiday $holiday)
    {
        $this->authorize('hr.dtr.manage');

        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
