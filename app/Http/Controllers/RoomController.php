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
    public function index(Request $request)
    {
        $q = Room::leftJoin('sections', 'rooms.section_id', '=', 'sections.id')
            ->select('rooms.*', 'sections.sectionname as section_name')
            ->with(['building','office'])
            ->orderBy('rooms.name');

        if ($request->has('building_id') && $request->query('building_id')) {
            $q->where('rooms.building_id', $request->query('building_id'));
        }

        $rooms = $q->get();

        // If this is a non-Inertia AJAX / API request, return JSON.
        // But do not return plain JSON for Inertia requests — Inertia expects
        // an Inertia response, otherwise the client will show a raw JSON error.
        $isInertia = $request->header('X-Inertia');
        if (($request->ajax() || $request->wantsJson()) && !$isInertia) {
            return response()->json($rooms);
        }

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
            'floor' => 'nullable|integer|min:1',
            'office_id' => 'nullable|exists:offices,id',
            'section_id' => 'nullable|exists:sections,id',
            'capacity' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
            'room_type' => 'nullable|in:Classroom,Admin,Laboratory,Sports/Recreation,Assembly,Comfort Room',
            'comfort_gender' => 'nullable|in:Female,Male,All Gender',
        ]);

        Room::create($request->only(['name','code','building_id','floor','office_id','capacity','remarks','room_type','comfort_gender','section_id']));

        return redirect()->route('rooms.index')->with('success', 'Room created');
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'building_id' => 'nullable|exists:buildings,id',
            'floor' => 'nullable|integer|min:1',
            'office_id' => 'nullable|exists:offices,id',
            'section_id' => 'nullable|exists:sections,id',
            'capacity' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
            'room_type' => 'nullable|in:Classroom,Admin,Laboratory,Sports/Recreation,Assembly,Comfort Room',
            'comfort_gender' => 'nullable|in:Female,Male,All Gender',
        ]);

        $room->update($request->only(['name','code','building_id','floor','office_id','capacity','remarks','room_type','comfort_gender','section_id']));

        return redirect()->route('rooms.index')->with('success', 'Room updated');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $room->delete();
        return redirect()->route('rooms.index')->with('success', 'Room deleted');
    }
}
