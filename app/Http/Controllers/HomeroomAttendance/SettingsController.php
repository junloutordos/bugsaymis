<?php

namespace App\Http\Controllers\HomeroomAttendance;

use App\Http\Controllers\Controller;
use App\Models\HomeroomAttendance\AttendanceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    // ── GET /homeroom-attendance/settings ──────────────────────────────────────

    public function edit()
    {
        return Inertia::render('HomeroomAttendance/Settings/Index', [
            'deductionPoints' => AttendanceSetting::deductionPoints(),
            'defaults'        => AttendanceSetting::DEDUCTION_POINTS_DEFAULT,
        ]);
    }

    // ── PATCH /homeroom-attendance/settings ────────────────────────────────────

    public function update(Request $request)
    {
        $data = $request->validate([
            'tardy'   => ['required', 'numeric', 'min:0', 'max:1'],
            'cutting' => ['required', 'numeric', 'min:0', 'max:1'],
            'absence' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        AttendanceSetting::updateOrCreate(
            ['setting_key' => AttendanceSetting::DEDUCTION_POINTS_KEY],
            ['value' => $data, 'updated_by' => Auth::id()],
        );

        return back()->with('success', 'Deduction points updated.');
    }
}
