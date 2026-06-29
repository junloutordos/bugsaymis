<?php

namespace App\Http\Controllers;

use App\Models\AtlasSentinelRelease;
use App\Models\ICTEquipment;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ComputerLabController extends Controller
{
    const LAB_ROWS = 5;
    const LAB_COLS = 6;

    public function index()
    {
        return Inertia::render('ITJobRequests/ComputerLabs/Index', [
            'labs' => self::summary(),
        ]);
    }

    /**
     * Per-lab unit/enrollment/risk counts — shared with the MIS Dashboard's
     * fleet health summary so both stay in sync off one query.
     */
    public static function summary()
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('name')->get();

        $equipments = ICTEquipment::where('category', 'CPU/System Unit')
            ->whereIn('room_id', $rooms->pluck('id'))
            ->with('agentDevice')
            ->get()
            ->groupBy('room_id');

        return $rooms->map(function ($room) use ($equipments) {
            $units = $equipments->get($room->id, collect());

            return [
                'room' => $room,
                'total' => $units->count(),
                'enrolled' => $units->filter(fn ($u) => $u->agentDevice)->count(),
                'critical' => $units->filter(fn ($u) => $u->agentDevice?->risk_tier === 'critical')->count(),
            ];
        })->values();
    }

    public function show(Room $room)
    {
        $equipments = ICTEquipment::where('room_id', $room->id)
            ->where('category', 'CPU/System Unit')
            ->with([
                'agentDevice.healthSnapshot',
                'agentDevice.hardwareInventory',
                'agentDevice.softwareInventory',
                'agentDevice.securityStatus',
            ])
            ->orderBy('lab_seat_row')
            ->orderBy('lab_seat_col')
            ->get();

        return Inertia::render('ITJobRequests/ComputerLabs/Show', [
            'room'               => $room,
            'equipments'         => $equipments,
            'rows'               => self::LAB_ROWS,
            'cols'               => self::LAB_COLS,
            'latestAgentVersion' => AtlasSentinelRelease::latestRelease()?->version,
        ]);
    }

    public function updateSeat(Request $request, ICTEquipment $equipment)
    {
        $validator = Validator::make($request->all(), [
            'lab_seat_row' => 'nullable|integer|min:1|max:' . self::LAB_ROWS,
            'lab_seat_col' => 'nullable|integer|min:1|max:' . self::LAB_COLS,
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if ($data['lab_seat_row'] !== null && $data['lab_seat_col'] !== null) {
            $occupied = ICTEquipment::where('room_id', $equipment->room_id)
                ->where('id', '<>', $equipment->id)
                ->where('lab_seat_row', $data['lab_seat_row'])
                ->where('lab_seat_col', $data['lab_seat_col'])
                ->exists();

            if ($occupied) {
                return response()->json(['message' => 'That seat is already taken.'], 422);
            }
        }

        $equipment->update($data);

        return response()->json(['equipment' => $equipment]);
    }
}
