<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\ScienceLab\LabCalibrationEvent;
use App\Models\ScienceLab\LabEquipment;
use App\Models\ScienceLab\LabEquipmentRequest;
use App\Models\ScienceLab\LabReagentRequest;
use App\Models\ScienceLab\LabRepairTicket;
use App\Models\ScienceLab\LabReservation;
use App\Models\ScienceLab\ScienceLabProfile;
use App\Services\ScienceLab\LabInventoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LabDashboardController extends Controller
{
    public function __construct(private LabInventoryService $inventory) {}

    public function index()
    {
        $today = Carbon::today();

        $equipmentByStatus = LabEquipment::selectRaw('status, COUNT(*) as c')
            ->groupBy('status')->pluck('c', 'status');

        $calibrationDue = LabCalibrationEvent::with('equipment:id,description,property_no')
            ->whereIn('status', ['scheduled', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today->copy()->addDays(30))
            ->orderBy('due_date')
            ->limit(10)->get();

        $lowStock     = $this->inventory->lowStock();
        $expiringSoon = $this->inventory->expiringSoon(30);

        $cards = [
            'laboratories'      => ScienceLabProfile::where('status', 'active')->count(),
            'equipment'         => LabEquipment::count(),
            'equipmentActive'   => (int) ($equipmentByStatus['active'] ?? 0),
            'equipmentDown'     => LabEquipment::whereIn('status', ['under_repair', 'out_of_service', 'for_disposal'])->count(),
            'openRepairs'       => LabRepairTicket::whereIn('status', ['open', 'in_repair'])->count(),
            'calibrationDue'    => LabCalibrationEvent::whereIn('status', ['scheduled', 'overdue'])
                                    ->whereNotNull('due_date')->whereDate('due_date', '<=', $today->copy()->addDays(30))->count(),
            'calibrationOverdue'=> LabCalibrationEvent::where('status', 'overdue')->count(),
            'lowStock'          => $lowStock->count(),
            'expiringSoon'      => $expiringSoon->count(),
            'pendingReservations' => LabReservation::whereIn('status', ['pending', 'endorsed'])->count(),
            'pendingEquipment'  => LabEquipmentRequest::whereIn('status', ['pending', 'endorsed', 'approved'])->count(),
            'pendingReagents'   => LabReagentRequest::whereIn('status', ['pending', 'endorsed', 'approved'])->count(),
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

        return Inertia::render('ScienceLab/Dashboard', [
            'cards'          => $cards,
            'calibrationDue' => $calibrationDue->map(fn ($e) => [
                'id' => $e->id,
                'equipment' => $e->equipment?->description,
                'property_no' => $e->equipment?->property_no,
                'due_date' => optional($e->due_date)->toDateString(),
                'status' => $e->status,
            ]),
            'lowStock'       => $lowStock->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name,
                'balance' => $this->inventory->balance($c->id),
                'reorder_level' => (float) $c->reorder_level,
                'unit' => $c->unit_of_measure,
            ])->values(),
            'expiringSoon'   => $expiringSoon->map(fn ($l) => [
                'id' => $l->id, 'name' => $l->consumable?->name,
                'lot_no' => $l->lot_no, 'quantity' => (float) $l->quantity,
                'expiry_date' => optional($l->expiry_date)->toDateString(),
            ])->values(),
            'upcoming'       => $upcoming,
            'can'            => [
                'manage'  => Auth::user()->hasPermission('lab.manage'),
                'reports' => Auth::user()->hasPermission('lab.reports'),
                'approveCalibration' => Auth::user()->hasPermission('lab.calibration.approve'),
            ],
        ]);
    }
}
