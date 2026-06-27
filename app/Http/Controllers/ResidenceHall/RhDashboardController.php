<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhApplication;
use App\Models\ResidenceHall\RhIntern;
use App\Models\ResidenceHall\RhLeavePass;
use App\Models\ResidenceHall\RhRoom;
use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RhDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('rh.dashboard.view');

        $hall = $request->user()->rh_hall;
        $currentSy = SchoolYear::where('is_current', true)->first();

        $stats = [];
        foreach (['BRH', 'GRH'] as $h) {
            if ($hall && $hall !== $h) {
                continue;
            }

            $roomIds = RhRoom::where('residence_hall', $h)->pluck('id');

            $stats[$h] = [
                'hall'              => $h,
                'label'             => $h === 'BRH' ? 'Boys Residence Hall' : 'Girls Residence Hall',
                'total_rooms'       => RhRoom::where('residence_hall', $h)->where('status', 'active')->count(),
                'active_interns'    => RhIntern::whereIn('rh_room_id', $roomIds)->where('status', 'active')->count(),
                'pending_apps'      => $currentSy
                    ? RhApplication::where('school_year_id', $currentSy->id)
                        ->where('preferred_hall', $h)
                        ->where('status', 'pending')
                        ->count()
                    : 0,
                'overdue_passes'    => RhLeavePass::whereHas('intern', fn ($q) => $q->whereIn('rh_room_id', $roomIds))
                    ->where('status', 'overdue')
                    ->count(),
            ];
        }

        $recentApplications = RhApplication::when($currentSy, fn ($q) => $q->where('school_year_id', $currentSy->id))
            ->when($hall, fn ($q) => $q->where('preferred_hall', $hall))
            ->whereIn('status', ['pending', 'evaluated'])
            ->latest()
            ->limit(8)
            ->get();

        // Attach student names
        $studentIds = $recentApplications->pluck('student_id')->unique()->values();
        $studentMap = $this->studentNameMap($studentIds);
        $recentApplications->transform(function ($a) use ($studentMap) {
            $a->student_name = $studentMap[$a->student_id] ?? ('Student #' . $a->student_id);
            return $a;
        });

        return Inertia::render('ResidenceHall/Dashboard', [
            'stats'              => array_values($stats),
            'recentApplications' => $recentApplications,
            'currentSy'          => $currentSy?->name,
            'myHall'             => $hall,
        ]);
    }

    private function studentNameMap($studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('students')
            ->whereIn('id', $studentIds)
            ->get(['id', 'lastname', 'firstname'])
            ->keyBy('id')
            ->map(fn ($s) => trim($s->lastname . ', ' . $s->firstname))
            ->toArray();
    }
}
