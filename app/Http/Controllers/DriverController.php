<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleRequest;
use App\Models\User;

class DriverController extends Controller
{
    /**
     * Return a list of drivers (users whose position contains 'Driver')
     */
    public function index()
    {
        $drivers = User::where('position', 'LIKE', '%Driver%')->orderBy('name')->get(['id', 'name', 'position']);
        return response()->json($drivers);
    }

    /**
     * Assign a driver to a vehicle request
     */
    public function assign(Request $request, VehicleRequest $vehicleRequest)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
        ]);
        $vehicleRequest->driver_id = $request->input('driver_id');
        $vehicleRequest->save();
        return response()->json(['success' => true]);
    }
}
