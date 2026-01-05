<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\VehicleRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\VehicleRequestCreatedMail;
use App\Models\User;

class VehicleRequestController extends Controller
{
    /**
     * Display a listing of vehicle requests.
     */
    public function index(Request $request)
    {

        $user = $request->user();
        $role = $user->role->name ?? '';

        $canViewAll = in_array($role, ['Administrator', 'GSU Head']);

        $requests = VehicleRequest::with('user:id,name')
            ->when(!$canViewAll, fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->get();

        // also fetch vehicles for dropdown (only available vehicles)
        $vehicles = \App\Models\Vehicle::where('status','!=','Under Repair')->orderBy('name')->get();

        // fetch users with DivisionChief role to allow requester to pick an approver
        $divisionChiefs = \App\Models\User::whereHas('role', fn($q) => $q->where('name', 'DivisionChief'))
            ->orderBy('name')
            ->get(['id','name']);

        return Inertia::render('VehicleRequests/Index', [
            'requests' => $requests,
            'vehicles' => $vehicles,
            'divisionChiefs' => $divisionChiefs,
        ]);
    }

    /**
     * Show the form for creating a new vehicle request.
     */
    public function create()
    {
        return redirect()->route('vehicle-requests.index');
    }

    /**
     * Store a new vehicle request
     */
    public function store(Request $request)
    {
        $request->validate([
            'purpose' => 'required|string|max:255',
            'destination' => 'nullable|string|max:255',
            // allow multiple dates as an array of dates
            'date_needed' => 'nullable|array',
            'date_needed.*' => 'date',
            'time_of_departure' => 'nullable|date_format:H:i',
            'eta' => 'nullable|date_format:H:i',
            'vehicle_type' => 'nullable|string|max:255',
            'passengers' => 'nullable|integer|min:1',
            'division_chief_id' => 'nullable|exists:users,id',
        ]);

        $user = $request->user();

        $vr = VehicleRequest::create([
            'user_id' => $user->id,
            'purpose' => $request->input('purpose'),
            'destination' => $request->input('destination'),
            // store first date in legacy `date_needed` and full array in `date_needed_multiple`
            'date_needed' => is_array($request->input('date_needed')) ? ($request->input('date_needed')[0] ?? null) : $request->input('date_needed'),
            'date_needed_multiple' => $request->input('date_needed'),
            'time_of_departure' => $request->input('time_of_departure'),
            'eta' => $request->input('eta'),
            'vehicle_type' => $request->input('vehicle_type'),
            'passengers' => $request->input('passengers') ?? 1,
            'division_chief_id' => $request->input('division_chief_id'),
            'status' => 'Pending',
        ]);

        // send notification email to selected division chief (if provided)
        if ($vr->division_chief_id) {
            $chief = User::find($vr->division_chief_id);
            if ($chief && $chief->email) {
                $approveUrl = URL::signedRoute('vehicle-requests.approve', ['vehicleRequest' => $vr->id, 'chief' => $chief->id], now()->addDays(7));
                $declineUrl = URL::signedRoute('vehicle-requests.decline', ['vehicleRequest' => $vr->id, 'chief' => $chief->id], now()->addDays(7));
                try {
                    Mail::to($chief->email)->send(new VehicleRequestCreatedMail($vr, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    // log but don't fail the request creation
                    logger()->error('Failed to send vehicle request email', ['error' => $e->getMessage()]);
                }
            }
        }

        return redirect()->route('vehicle-requests.index');
    }

    /**
     * Approve vehicle request by Division Chief via signed link
     */
    public function approveByDivisionChief(Request $request, VehicleRequest $vehicleRequest, $chief)
    {
        // Signed route ensures URL integrity. Verify that the chief in the link matches the assigned approver.
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        // If already approved, show a friendly message and do not change state.
        if ($vehicleRequest->status === 'Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        $vehicleRequest->status = 'Approved';
        $vehicleRequest->save();

        // Notify requester via email
        $requester = $vehicleRequest->user;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Approved'));
            } catch (\Throwable $e) {
                \Log::error('Failed to send vehicle request approved notification', ['error' => $e->getMessage()]);
            }
        }

        // Notify all GSU Head users
        $gsuHeads = \App\Models\User::whereHas('role', function($q) { $q->where('name', 'GSU Head'); })->get();
        foreach ($gsuHeads as $gsuHead) {
            if ($gsuHead->email) {
                try {
                    \Mail::to($gsuHead->email)->send(new \App\Mail\VehicleRequestGSUHeadMail($vehicleRequest));
                } catch (\Throwable $e) {
                    \Log::error('Failed to send GSU Head vehicle request notification', ['error' => $e->getMessage()]);
                }
            }
        }

        return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => false]);
    }

    /**
     * Show decline form for signed decline link
     */
    public function showDeclineForm(Request $request, VehicleRequest $vehicleRequest, $chief)
    {
        // Ensure the chief matches the assigned approver
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        // If already approved, show the approved page
        if ($vehicleRequest->status === 'Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        // Build POST action preserving signature query parameters so signed middleware validates POST as well
        $postAction = route('vehicle-requests.decline.submit', ['vehicleRequest' => $vehicleRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('vehicle_request_decline', ['vehicleRequest' => $vehicleRequest, 'postAction' => $postAction]);
    }

    /**
     * Handle decline submission
     */
    public function submitDecline(Request $request, VehicleRequest $vehicleRequest, $chief)
    {
        // Ensure the chief matches the assigned approver
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        // If already processed
        if (in_array($vehicleRequest->status, ['Approved','Declined'])) {
            $reason = $vehicleRequest->decline_reason ?? '—';
            return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $reason]);
        }

        $vehicleRequest->status = 'Declined';
        $vehicleRequest->decline_reason = $request->input('reason');
        $vehicleRequest->declined_at = now();
        $vehicleRequest->save();

        // Notify requester via email
        $requester = $vehicleRequest->user;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', $vehicleRequest->decline_reason));
            } catch (\Throwable $e) {
                \Log::error('Failed to send vehicle request declined notification', ['error' => $e->getMessage()]);
            }
        }

        return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $vehicleRequest->decline_reason]);
    }

    /**
     * Update the specified vehicle request (admin only)
     */
    public function update(Request $request, VehicleRequest $vehicleRequest)
    {
        $isAdmin = ($request->user()->role->name ?? '') === 'Administrator';
        if (! $isAdmin) {
            abort(403);
        }


        $request->validate([
            'purpose' => 'required|string|max:255',
            'destination' => 'nullable|string|max:255',
            'date_needed' => 'nullable|array',
            'date_needed.*' => 'date',
            'time_of_departure' => 'nullable|date_format:H:i',
            'eta' => 'nullable|date_format:H:i',
            'vehicle_type' => 'nullable|string|max:255',
            'passengers' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:255',
            'division_chief_id' => 'nullable|exists:users,id',
        ]);

        // Update both legacy single date and the new multiple dates json column
        $data = $request->only(['purpose','destination','vehicle_type','passengers','status','division_chief_id']);
        $dates = $request->input('date_needed');
        if (is_array($dates)) {
            $data['date_needed_multiple'] = $dates;
            $data['date_needed'] = $dates[0] ?? null;
        } else {
            $data['date_needed'] = $request->input('date_needed');
        }
        $data['time_of_departure'] = $request->input('time_of_departure');
        $data['eta'] = $request->input('eta');

        $vehicleRequest->update($data);

        return redirect()->route('vehicle-requests.index');
    }

    /**
     * Remove the specified vehicle request (admin only)
     */
    public function destroy(VehicleRequest $vehicleRequest)
    {
        $isAdmin = (auth()->user()->role->name ?? '') === 'Administrator';
        if (! $isAdmin) {
            abort(403);
        }
        $vehicleRequest->delete();
        return redirect()->route('vehicle-requests.index');
    }
}
