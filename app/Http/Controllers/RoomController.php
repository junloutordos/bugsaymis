<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Room;
use App\Models\Building;
use App\Models\Office;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::with(['building','office'])->orderBy('name')->get();
        $buildings = Building::orderBy('name')->get();
        $offices = Office::orderBy('name')->select('id','name')->get();

        return Inertia::render('DataManagement/Rooms/Index', [
            'rooms' => $rooms,
            'buildings' => $buildings,
            'offices' => $offices,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'building_id' => 'nullable|exists:buildings,id',
            'office_id' => 'nullable|exists:offices,id',
            'capacity' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        Room::create($request->only(['name','code','building_id','office_id','capacity','remarks']));

        return redirect()->route('rooms.index')->with('success', 'Room created');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'building_id' => 'nullable|exists:buildings,id',
            'office_id' => 'nullable|exists:offices,id',
            'capacity' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        $room->update($request->only(['name','code','building_id','office_id','capacity','remarks']));

        return redirect()->route('rooms.index')->with('success', 'Room updated');
    }

    public function destroy(Room $room): Response
    {
        $room->delete();
        return response('Deleted', 200);
    }
}
