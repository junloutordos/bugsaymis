<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = DB::table('attendance_clean')->orderByDesc('AttDate')->orderBy('BadgeNumber');

        $role = $user?->role?->name ?? null;
        if (in_array($role, ['Staff', 'Faculty'])) {
            $badge = $user->badge_id ?? null;
            if ($badge) $query->where('BadgeNumber', $badge);
            else $query->whereRaw('0 = 1');
        }

        $attendances = $query->paginate(25);

        return Inertia::render('HumanResource/Attendance/Index', [
            'attendances' => $attendances,
        ]);
    }
}
