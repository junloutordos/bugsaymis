<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhRoom;
use Illuminate\Http\Request;
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
            return back()->with('error', 'Cannot delete a room with active interns.');
        }

        $rhRoom->delete();

        return back()->with('success', 'Room deleted.');
    }
}
