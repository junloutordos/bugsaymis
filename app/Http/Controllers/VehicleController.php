<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Administrator');
    }

    public function index()
    {
        $vehicles = Vehicle::orderBy('name')->get();
        return Inertia::render('Vehicles/Index', [
            'vehicles' => $vehicles,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:vehicles,name',
            'plate_number' => 'nullable|string|max:255|unique:vehicles,plate_number',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|string|in:Good Working,Under Repair',
        ]);

        Vehicle::create($request->only(['name','plate_number','description','capacity','status']));

        return redirect()->route('vehicles.index')->with('success','Vehicle added.');
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:vehicles,name,'.$vehicle->id,
            'plate_number' => 'nullable|string|max:255|unique:vehicles,plate_number,'.$vehicle->id,
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'status' => 'required|string|in:Good Working,Under Repair',
        ]);
        $vehicle->update($request->only(['name','plate_number','description','capacity','status']));
        return redirect()->route('vehicles.index')->with('success','Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return redirect()->route('vehicles.index')->with('success','Vehicle deleted.');
    }
}
