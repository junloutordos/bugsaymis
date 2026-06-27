<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhIntern;
use App\Models\ResidenceHall\RhRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RhRoomController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('rh.rooms.manage');

        $hall = $request->user()->rh_hall;

        $rooms = RhRoom::withCount(['activeInterns'])
            ->when($hall, fn ($q) => $q->where('residence_hall', $hall))
            ->orderBy('residence_hall')
            ->orderBy('room_number')
            ->get();

        return Inertia::render('ResidenceHall/Rooms/Index', [
            'rooms'  => $rooms,
            'myHall' => $hall,
        ]);
    }

    public function show(Request $request, RhRoom $rhRoom)
    {
        $this->authorize('rh.rooms.manage');

        $hall = $request->user()->rh_hall;
        if ($hall && $rhRoom->residence_hall !== $hall) {
            abort(403);
        }

        // Active dormers in this room with bed assignments
        $internRows = RhIntern::where('rh_room_id', $rhRoom->id)
            ->where('status', 'active')
            ->get(['id', 'student_id', 'bed_number']);

        $studentIds = $internRows->pluck('student_id')->unique()->values();
        $nameMap = $studentIds->isNotEmpty()
            ? DB::table('students')
                ->whereIn('id', $studentIds)
                ->get(['id', 'lastname', 'firstname'])
                ->keyBy('id')
                ->map(fn($s) => trim($s->lastname . ', ' . $s->firstname))
                ->toArray()
            : [];

        $dormers = $internRows->map(fn($i) => [
            'id'         => $i->id,
            'name'       => $nameMap[$i->student_id] ?? ('Dormer #' . $i->id),
            'bed_number' => $i->bed_number,
        ]);

        // Unassigned active dormers in this hall (no bed_number set in this room)
        $assignedIds = $internRows->pluck('id');
        $unassigned  = RhIntern::whereHas('room', fn($q) => $q->where('residence_hall', $rhRoom->residence_hall))
            ->where('status', 'active')
            ->whereNotIn('id', $assignedIds)
            ->whereNull('bed_number')
            ->orWhere(fn($q) => $q
                ->where('rh_room_id', '!=', $rhRoom->id)
                ->where('status', 'active')
                ->whereNull('bed_number')
                ->whereHas('room', fn($r) => $r->where('residence_hall', $rhRoom->residence_hall))
            )
            ->get(['id', 'student_id'])
            ->unique('id');

        $unStudentIds = $unassigned->pluck('student_id')->unique()->values();
        $unNameMap = $unStudentIds->isNotEmpty()
            ? DB::table('students')
                ->whereIn('id', $unStudentIds)
                ->get(['id', 'lastname', 'firstname'])
                ->keyBy('id')
                ->map(fn($s) => trim($s->lastname . ', ' . $s->firstname))
                ->toArray()
            : [];

        $unassignedList = $unassigned->map(fn($i) => [
            'id'   => $i->id,
            'name' => $unNameMap[$i->student_id] ?? ('Dormer #' . $i->id),
        ])->values();

        return Inertia::render('ResidenceHall/Rooms/Show', [
            'room'           => $rhRoom,
            'dormers'        => $dormers,
            'unassignedList' => $unassignedList,
            'myHall'         => $hall,
        ]);
    }

    public function assignBed(Request $request, RhRoom $rhRoom)
    {
        $this->authorize('rh.rooms.manage');

        $hall = $request->user()->rh_hall;
        if ($hall && $rhRoom->residence_hall !== $hall) abort(403);

        $data = $request->validate([
            'rh_intern_id' => ['required', 'exists:rh_interns,id'],
            'bed_number'   => ['required', 'string', 'max:10'],
        ]);

        // Ensure bed is not already taken in this room
        $taken = RhIntern::where('rh_room_id', $rhRoom->id)
            ->where('status', 'active')
            ->where('bed_number', $data['bed_number'])
            ->where('id', '!=', $data['rh_intern_id'])
            ->exists();

        if ($taken) {
            return back()->with('error', 'That bed is already occupied.');
        }

        RhIntern::where('id', $data['rh_intern_id'])->update([
            'rh_room_id'  => $rhRoom->id,
            'bed_number'  => $data['bed_number'],
        ]);

        return back()->with('success', 'Bed assigned.');
    }

    public function unassignBed(Request $request, RhRoom $rhRoom)
    {
        $this->authorize('rh.rooms.manage');

        $hall = $request->user()->rh_hall;
        if ($hall && $rhRoom->residence_hall !== $hall) abort(403);

        $data = $request->validate([
            'rh_intern_id' => ['required', 'exists:rh_interns,id'],
        ]);

        RhIntern::where('id', $data['rh_intern_id'])
            ->where('rh_room_id', $rhRoom->id)
            ->update(['bed_number' => null]);

        return back()->with('success', 'Bed unassigned.');
    }

    public function store(Request $request)
    {
        $this->authorize('rh.rooms.manage');

        $validated = $request->validate([
            'residence_hall' => ['required', 'in:BRH,GRH'],
            'room_number'    => ['required', 'string', 'max:20'],
            'capacity'       => ['required', 'integer', 'min:1', 'max:20'],
            'floor'          => ['nullable', 'integer', 'min:1'],
            'description'    => ['nullable', 'string', 'max:255'],
            'status'         => ['required', 'in:active,inactive'],
        ]);

        // Enforce hall scope for dorm managers
        $hall = $request->user()->rh_hall;
        if ($hall && $validated['residence_hall'] !== $hall) {
            abort(403, 'You can only manage your assigned hall.');
        }

        RhRoom::create($validated);

        return back()->with('success', 'Room added successfully.');
    }

    public function update(Request $request, RhRoom $rhRoom)
    {
        $this->authorize('rh.rooms.manage');

        $hall = $request->user()->rh_hall;
        if ($hall && $rhRoom->residence_hall !== $hall) {
            abort(403);
        }

        $validated = $request->validate([
            'room_number' => ['required', 'string', 'max:20'],
            'capacity'    => ['required', 'integer', 'min:1', 'max:20'],
            'floor'       => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'status'      => ['required', 'in:active,inactive'],
        ]);

        $rhRoom->update($validated);

        return back()->with('success', 'Room updated.');
    }

    public function destroy(RhRoom $rhRoom)
    {
        $this->authorize('rh.rooms.manage');

        if ($rhRoom->activeInterns()->exists()) {
            return back()->with('error', 'Cannot delete a room with active dormers.');
        }

        $rhRoom->delete();

        return back()->with('success', 'Room deleted.');
    }
}
