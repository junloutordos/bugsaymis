<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $q = $request->query('q');

        $query = Schedule::query();
        if ($user && $user->hasAnyRole(['Staff', 'Faculty'])) {
            $badge = $user->badge_id ?? null;
            $query->where('badgeNumber', $badge);
        }

        if ($q) {
            $query->where(function($s) use ($q) {
                $s->where('m_timein', 'like', "%$q%")
                  ->orWhere('t_timein', 'like', "%$q%")
                  ->orWhere('w_timein', 'like', "%$q%");
            });
        }

        $schedules = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        return Inertia::render('HumanResource/Schedule/Index', [
            'schedules' => $schedules,
            'q' => $q,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        // Allow Administrator, Staff, and Faculty to create their own schedules
        if (!($user && $user->hasAnyRole(['Administrator', 'Staff', 'Faculty']))) {
            return redirect()->back()->with('error', 'Unauthorized');
        }

        $data = $request->validate([
            'm_timein' => 'nullable|string', 'm_breakout' => 'nullable|string', 'm_breakin' => 'nullable|string', 'm_timeout' => 'nullable|string',
            't_timein' => 'nullable|string', 't_breakout' => 'nullable|string', 't_breakin' => 'nullable|string', 't_timeout' => 'nullable|string',
            'w_timein' => 'nullable|string', 'w_breakout' => 'nullable|string', 'w_breakin' => 'nullable|string', 'w_timeout' => 'nullable|string',
            'th_timein' => 'nullable|string', 'th_breakout' => 'nullable|string', 'th_breakin' => 'nullable|string', 'th_timeout' => 'nullable|string',
            'f_timein' => 'nullable|string', 'f_breakout' => 'nullable|string', 'f_breakin' => 'nullable|string', 'f_timeout' => 'nullable|string',
            'sat_timein' => 'nullable|string', 'sat_breakout' => 'nullable|string', 'sat_breakin' => 'nullable|string', 'sat_timeout' => 'nullable|string',
            'sun_timein' => 'nullable|string', 'sun_breakout' => 'nullable|string', 'sun_breakin' => 'nullable|string', 'sun_timeout' => 'nullable|string',
        ]);

        $data['badgeNumber'] = $user->badge_id ?? null;

        // prevent multiple schedules for the same badgeNumber
        if ($data['badgeNumber'] && Schedule::where('badgeNumber', $data['badgeNumber'])->exists()) {
            throw ValidationException::withMessages(['badgeNumber' => ['A schedule for this badge number already exists.']]);
        }

        Schedule::create($data);

        return redirect()->route('schedules.index');
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        // Only admin may update
        if (!($user && $user->hasRole('Administrator'))) {
            return redirect()->back()->with('error', 'Unauthorized');
        }

        $sched = Schedule::findOrFail($id);

        $data = $request->validate([
            'm_timein' => 'nullable|string', 'm_breakout' => 'nullable|string', 'm_breakin' => 'nullable|string', 'm_timeout' => 'nullable|string',
            't_timein' => 'nullable|string', 't_breakout' => 'nullable|string', 't_breakin' => 'nullable|string', 't_timeout' => 'nullable|string',
            'w_timein' => 'nullable|string', 'w_breakout' => 'nullable|string', 'w_breakin' => 'nullable|string', 'w_timeout' => 'nullable|string',
            'th_timein' => 'nullable|string', 'th_breakout' => 'nullable|string', 'th_breakin' => 'nullable|string', 'th_timeout' => 'nullable|string',
            'f_timein' => 'nullable|string', 'f_breakout' => 'nullable|string', 'f_breakin' => 'nullable|string', 'f_timeout' => 'nullable|string',
            'sat_timein' => 'nullable|string', 'sat_breakout' => 'nullable|string', 'sat_breakin' => 'nullable|string', 'sat_timeout' => 'nullable|string',
            'sun_timein' => 'nullable|string', 'sun_breakout' => 'nullable|string', 'sun_breakin' => 'nullable|string', 'sun_timeout' => 'nullable|string',
        ]);

        $sched->update($data);

        return redirect()->route('schedules.index');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (!($user && $user->hasRole('Administrator'))) {
            return redirect()->back()->with('error', 'Unauthorized');
        }
        $sched = Schedule::findOrFail($id);
        $sched->delete();
        return redirect()->route('schedules.index');
    }
}
