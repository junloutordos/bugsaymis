<?php

namespace App\Http\Controllers;

use App\Models\Building;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BuildingController extends Controller
{
    public function index()
    {
        $buildings = Building::select('buildings.*', DB::raw('(select count(distinct users.id) from users inner join offices on users.office_id = offices.id inner join rooms on rooms.office_id = offices.id where rooms.building_id = buildings.id) as occupants_count'))
            ->orderBy('id', 'desc')
            ->get();
        return Inertia::render('DataManagement/Buildings/Index', [
            'buildings' => $buildings,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'no_of_rooms' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        Building::create($data);

        return redirect()->route('buildings.index')->with('success', 'Building created.');
    }

    public function update(Request $request, Building $building)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'no_of_rooms' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string',
        ]);

        $building->update($data);

        return redirect()->route('buildings.index')->with('success', 'Building updated.');
    }

    public function destroy(Building $building)
    {
        $building->delete();
        return redirect()->route('buildings.index')->with('success', 'Building deleted.');
    }
}
