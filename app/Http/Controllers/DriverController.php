<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

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
        // After assigning a driver, notify OCD users so they can take action
        $ocdUsers = User::whereHas('role', function($q) { $q->where('name', 'OCD'); })->get();
        foreach ($ocdUsers as $ocdUser) {
            if ($ocdUser->email) {
                try {
                    $approveUrl = URL::signedRoute('vehicle-requests.ocd.approve', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocdUser->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('vehicle-requests.ocd.decline', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocdUser->id], now()->addDays(7));
                    Mail::to($ocdUser->email)->send(new \App\Mail\VehicleRequestOCDMail($vehicleRequest, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    \Log::error('Failed to send OCD notification after driver assignment', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['success' => true]);
    }
}
