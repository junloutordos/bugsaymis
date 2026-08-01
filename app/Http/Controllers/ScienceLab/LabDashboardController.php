<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\CsmResponse;
use App\Models\ScienceLab\LabCalibrationEvent;
use App\Models\ScienceLab\LabEquipment;
use App\Models\ScienceLab\LabEquipmentRequest;
use App\Models\ScienceLab\LabReagentRequest;
use App\Models\ScienceLab\LabRequestBundle;
use App\Models\ScienceLab\LabReservation;
use App\Models\ScienceLab\ScienceLabProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LabDashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $cards = [
            'laboratories' => ScienceLabProfile::where('status', 'active')->count(),
            'equipment'    => LabEquipment::count(),
            'requests'     => [
                'reservation' => $this->requestCounts(LabReservation::class),
                'equipment'   => $this->requestCounts(LabEquipmentRequest::class),
                'reagent'     => $this->requestCounts(LabReagentRequest::class),
            ],
        ];

        $upcoming = LabReservation::with('room:id,name')
            ->whereIn('status', ['approved', 'endorsed'])
            ->whereDate('date_start', '>=', $today)
            ->orderBy('date_start')->orderBy('time_start')
            ->limit(8)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'control_no' => $r->control_no,
                'room' => $r->room?->name, 'subject' => $r->subject,
                'date' => optional($r->date_start)->toDateString(),
                'time_start' => $r->time_start, 'status' => $r->status,
            ]);

        $calendarReservations = LabReservation::with('room:id,name')
            ->whereIn('status', ['approved', 'completed'])
            ->whereDate('date_start', '<=', $today->copy()->endOfMonth())
            ->where(fn ($query) => $query->whereNull('date_end')->whereDate('date_start', '>=', $today->copy()->startOfMonth())
                ->orWhereDate('date_end', '>=', $today->copy()->startOfMonth()))
            ->orderBy('date_start')->orderBy('time_start')->get()
            ->map(fn ($reservation) => [
                'id' => $reservation->id,
                'room' => $reservation->room?->name,
                'subject' => $reservation->subject,
                'date_start' => optional($reservation->date_start)->toDateString(),
                'date_end' => optional($reservation->date_end)->toDateString(),
                'time_start' => $reservation->time_start,
            ]);

        $csmResponses = CsmResponse::where('respondable_type', LabRequestBundle::class)->get();
        $csmScores = $csmResponses->map(fn (CsmResponse $response) => $response->derivedRating())->filter();

        $monthly = collect(range(5, 0))->map(function (int $monthsAgo) {
            $month = now()->startOfMonth()->subMonths($monthsAgo);

            return [
                'label' => $month->format('M'),
                'requests' => LabRequestBundle::whereBetween('created_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ])->count(),
            ];
        });

        return Inertia::render('ScienceLab/Dashboard', [
            'cards'    => $cards,
            'upcoming' => $upcoming,
            'calendar' => $calendarReservations,
            'calendarMonth' => $today->format('Y-m'),
            'csm'      => [
                'responses' => $csmResponses->count(),
                'average'   => $csmScores->isEmpty() ? null : round($csmScores->average(), 2),
                'pending'   => LabRequestBundle::where('status', 'completed')->whereNull('csm_completed_at')->count(),
            ],
            'monthly' => $monthly,
            'can'            => [
                'manage'  => Auth::user()->hasPermission('lab.manage'),
                'reports' => Auth::user()->hasPermission('lab.reports'),
                'approveCalibration' => Auth::user()->hasPermission('lab.calibration.approve'),
            ],
        ]);
    }

    private function requestCounts(string $model): array
    {
        return [
            'total'    => $model::count(),
            'pending'  => $model::whereIn('status', ['pending', 'pending_endorsement', 'endorsed', 'pending_approval'])->count(),
            'approved' => $model::whereIn('status', ['approved', 'issued', 'returned', 'released', 'completed'])->count(),
        ];
    }
}
