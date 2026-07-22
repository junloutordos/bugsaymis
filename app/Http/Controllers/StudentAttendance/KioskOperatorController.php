<?php

namespace App\Http\Controllers\StudentAttendance;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance\StudentAttendanceKioskOperator;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class KioskOperatorController extends Controller
{
    public function setPin(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
        ]);

        abort_unless($user->hasRole('Security Guard'), 422, 'Only Security Guard users can receive a kiosk PIN.');

        StudentAttendanceKioskOperator::updateOrCreate(
            ['user_id' => $user->id],
            [
                'pin_hash' => Hash::make($validated['pin']),
                'is_active' => true,
                'set_by' => $request->user()->id,
                'pin_changed_at' => now(),
            ],
        );

        return back()->with('success', "Gate scanner PIN set for {$user->name}.");
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $credential = StudentAttendanceKioskOperator::where('user_id', $user->id)->firstOrFail();
        $credential->update(['is_active' => $validated['is_active']]);

        return back()->with('success', $validated['is_active']
            ? "Gate scanner access enabled for {$user->name}."
            : "Gate scanner access disabled for {$user->name}.");
    }
}
