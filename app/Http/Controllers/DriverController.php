<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

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
            'vehicle_id' => 'nullable|exists:vehicles,id',
        ]);

        // Build dates array from request
        $dates = [];
        if (is_array($vehicleRequest->date_needed_multiple) && count($vehicleRequest->date_needed_multiple) > 0) {
            $dates = $vehicleRequest->date_needed_multiple;
        } elseif (! empty($vehicleRequest->date_needed)) {
            $dates = [
                ($vehicleRequest->date_needed instanceof Carbon) ? $vehicleRequest->date_needed->format('Y-m-d') : date('Y-m-d', strtotime($vehicleRequest->date_needed))
            ];
        }

        $timeStart = $vehicleRequest->time_of_departure;
        $timeEnd = $vehicleRequest->eta;

        // Determine vehicle name to check conflicts against
        $vehicleName = $vehicleRequest->vehicle_type;
        if ($request->filled('vehicle_id')) {
            $v = \App\Models\Vehicle::find($request->input('vehicle_id'));
            if ($v) $vehicleName = $v->name;
        }

        // Vehicle availability check
        $vehicleConflicts = [];
        if ($vehicleName && count($dates) > 0) {
            foreach ($dates as $d) {
                $existing = VehicleRequest::where('vehicle_type', $vehicleName)
                    ->where('id', '!=', $vehicleRequest->id)
                    ->where('status', 'OCD Approved')
                    ->where(function ($q) use ($d) {
                        $q->whereDate('date_needed', $d)
                          ->orWhereJsonContains('date_needed_multiple', $d);
                    })
                    ->get();

                foreach ($existing as $ex) {
                    if (empty($ex->time_of_departure) || empty($ex->eta) || empty($timeStart) || empty($timeEnd)) {
                        $vehicleConflicts[] = $d;
                        break;
                    }
                    try {
                        $exStart = Carbon::createFromFormat('H:i:s', $ex->time_of_departure)->format('H:i');
                        $exEnd = Carbon::createFromFormat('H:i:s', $ex->eta)->format('H:i');
                    } catch (\Throwable $e) {
                        $exStart = substr($ex->time_of_departure,0,5);
                        $exEnd = substr($ex->eta,0,5);
                    }
                    $nStart = Carbon::createFromFormat('H:i', $timeStart)->format('H:i');
                    $nEnd = Carbon::createFromFormat('H:i', $timeEnd)->format('H:i');

                    if ($nStart < $exEnd && $nEnd > $exStart) {
                        $vehicleConflicts[] = $d;
                        break;
                    }
                }
            }
        }

        if (! empty($vehicleConflicts)) {
            $unique = array_values(array_unique($vehicleConflicts));
            return response()->json([
                'type' => 'vehicle',
                'message' => "The vehicle {$vehicleName} is already booked on the following date(s): " . implode(', ', $unique),
                'dates' => $unique,
            ], 422);
        }

        // Driver availability check
        $driverId = $request->input('driver_id');
        $driverConflicts = [];
        if ($driverId && count($dates) > 0) {
            foreach ($dates as $d) {
                $existing = VehicleRequest::where('driver_id', $driverId)
                    ->where('id', '!=', $vehicleRequest->id)
                    ->where('status', 'OCD Approved')
                    ->where(function ($q) use ($d) {
                        $q->whereDate('date_needed', $d)
                          ->orWhereJsonContains('date_needed_multiple', $d);
                    })
                    ->get();

                foreach ($existing as $ex) {
                    if (empty($ex->time_of_departure) || empty($ex->eta) || empty($timeStart) || empty($timeEnd)) {
                        $driverConflicts[] = $d;
                        break;
                    }
                    try {
                        $exStart = Carbon::createFromFormat('H:i:s', $ex->time_of_departure)->format('H:i');
                        $exEnd = Carbon::createFromFormat('H:i:s', $ex->eta)->format('H:i');
                    } catch (\Throwable $e) {
                        $exStart = substr($ex->time_of_departure,0,5);
                        $exEnd = substr($ex->eta,0,5);
                    }
                    $nStart = Carbon::createFromFormat('H:i', $timeStart)->format('H:i');
                    $nEnd = Carbon::createFromFormat('H:i', $timeEnd)->format('H:i');

                    if ($nStart < $exEnd && $nEnd > $exStart) {
                        $driverConflicts[] = $d;
                        break;
                    }
                }
            }
        }

        if (! empty($driverConflicts)) {
            $unique = array_values(array_unique($driverConflicts));
            return response()->json([
                'type' => 'driver',
                'message' => "The selected driver is already booked on the following date(s): " . implode(', ', $unique),
                'dates' => $unique,
            ], 422);
        }

        // Persist assignment and optional vehicle change
        $vehicleRequest->driver_id = $driverId;
        if ($request->filled('vehicle_id')) {
            $v2 = \App\Models\Vehicle::find($request->input('vehicle_id'));
            if ($v2) $vehicleRequest->vehicle_type = $v2->name;
        }
        $vehicleRequest->save();

        // After assigning a driver, notify OCD users so they can take action
        $ocdUsers = User::havingRole('OCD')->get();
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
