<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Arrange attendance logs by date (ascending) then by badge number
        $query = DB::table('attendance_clean')->orderBy('AttDate')->orderBy('BadgeNumber');

        // Apply date range filtering when provided via query params `start` and `end` (YYYY-MM-DD)
        try {
            $start = $request->query('start');
            $end = $request->query('end');
            if ($start) $startDate = Carbon::parse($start)->format('Y-m-d');
            if ($end) $endDate = Carbon::parse($end)->format('Y-m-d');

            if (!empty($startDate) && !empty($endDate)) {
                if ($startDate > $endDate) {
                    // swap to ensure proper range
                    [$startDate, $endDate] = [$endDate, $startDate];
                }
                $query->whereBetween('AttDate', [$startDate, $endDate]);
            } elseif (!empty($startDate)) {
                $query->where('AttDate', '>=', $startDate);
            } elseif (!empty($endDate)) {
                $query->where('AttDate', '<=', $endDate);
            }
        } catch (\Throwable $e) {
            // ignore parse errors and continue without date filtering
        }

        $role = $user?->role?->name ?? null;
        if (in_array($role, ['Staff', 'Faculty'])) {
            $badge = $user->badge_id ?? null;
            if ($badge) $query->where('BadgeNumber', $badge);
            else $query->whereRaw('0 = 1');
        }

        // show 15 rows per page for a cleaner layout
        $attendances = $query->paginate(15);

        return Inertia::render('HumanResource/Attendance/Index', [
            'attendances' => $attendances,
        ]);
    }
}
