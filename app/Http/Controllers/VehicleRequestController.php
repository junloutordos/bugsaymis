<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\VehicleRequest;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Mail\VehicleRequestCreatedMail;
use App\Models\User;
use Carbon\Carbon;
use App\Enums\ApprovalStep;
use App\Services\SnapshotService;
use App\Services\DigitalSignatureService;
use App\Http\Traits\SignsDocuments;

class VehicleRequestController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private SnapshotService         $snapshots,
        private DigitalSignatureService $sigService,
    ) {}
    /**
     * Display a listing of vehicle requests.
     */
    public function index(Request $request)
    {

        $user = $request->user();
        $canViewAll = $user->hasAnyRole(['Administrator', 'GSU Head', 'OCD']);

        $requests = VehicleRequest::with(['requester:id,name', 'driver:id,name'])->latest();

        if (! $canViewAll) {
            if ($user->hasRole('DivisionChief')) {
                $requests->where(function ($q) use ($user) {
                    $q->where('requestor_id', $user->id)
                      ->orWhere('division_chief_id', $user->id);
                });
            } else {
                $requests->where('requestor_id', $user->id);
            }
        }

        $requests = $requests->get();

        // also fetch vehicles for dropdown (only available vehicles)
        $vehicles = \App\Models\Vehicle::where('status','!=','Under Repair')->orderBy('name')->get();

        // fetch users with DivisionChief role to allow requester to pick an approver
        $divisionChiefs = \App\Models\User::havingRole('DivisionChief')
            ->orderBy('name')
            ->get(['id','name']);

        $isDivisionChief = $user->hasRole('DivisionChief');

        // Server-side check: does this user have any OCD Approved (unrated) vehicle requests?
        $hasPendingCsm = VehicleRequest::where('requestor_id', $user->id)
            ->where('status', 'OCD Approved')
            ->exists();

        return Inertia::render('VehicleRequests/Index', [
            'requests'        => $requests,
            'vehicles'        => $vehicles,
            'divisionChiefs'  => $divisionChiefs,
            'isDivisionChief' => $isDivisionChief,
            'hasPendingCsm'   => $hasPendingCsm,
            'hasPin'          => ! empty($user->signature_pin),
            'signatureUri'    => $this->sigService->getSignatureDataUri($user),
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
                'destination' => 'required|string|max:255',
            // allow multiple dates as an array of dates
                'date_needed' => 'required|array|min:1',
            'date_needed.*' => 'date',
                'time_of_departure' => 'required|date_format:H:i',
                'eta' => 'required|date_format:H:i',
                'vehicle_type' => 'required|string|max:255',
                'passengers' => 'required|integer|min:1',
                'division_chief_id' => 'required|exists:users,id',
        ]);

        $user = $request->user();

        // prepare dates array
        $dates = is_array($request->input('date_needed')) ? $request->input('date_needed') : ($request->input('date_needed') ? [$request->input('date_needed')] : []);

        // conflict detection: ensure the requested vehicle isn't already booked for any of the requested dates/time
        $vehicleName = $request->input('vehicle_type');
        $timeStart = $request->input('time_of_departure');
        $timeEnd = $request->input('eta');
        $vehicleConflicts = [];
        if ($vehicleName && count($dates) > 0) {
            foreach ($dates as $d) {
                $existing = VehicleRequest::where('vehicle_type', $vehicleName)
                    ->where('status', '!=', 'Declined')
                    ->where(function ($q) use ($d) {
                        $q->whereDate('date_needed', $d)
                          ->orWhereJsonContains('date_needed_multiple', $d);
                    })
                    ->get();

                foreach ($existing as $ex) {
                    // treat missing times as conflict
                    if (empty($ex->time_of_departure) || empty($ex->eta) || empty($timeStart) || empty($timeEnd)) {
                        $vehicleConflicts[] = $d;
                        break;
                    }

                    // compare times (H:i)
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
            $datesStr = implode(', ', array_unique($vehicleConflicts));
            return redirect()->back()->withInput()->withErrors(['vehicle' => "The vehicle {$vehicleName} is already booked on: {$datesStr}"]);
        }

        $vr = VehicleRequest::create([
            'requestor_id' => $user->id,
            'purpose' => $request->input('purpose'),
            'destination' => $request->input('destination'),
            // store first date in legacy `date_needed` and full array in `date_needed_multiple`
            'date_needed' => $dates[0] ?? null,
            'date_needed_multiple' => $dates,
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

        $this->performSign($request, VehicleRequest::class, $vr->id,
            'submission',
            "Vehicle Request #{$vr->id}",
            VehicleRequest::class . $vr->id . 'submission'
        );

        return redirect()->route('vehicle-requests.index');
    }

    // Authenticated in-app approval by logged-in Division Chief
    public function approveInApp(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();
        logger()->info('approveInApp called', ['user_id' => $user->id ?? null, 'role' => $user->getRoleName(), 'vehicle_request_id' => $vehicleRequest->id]);

        if (! $user || ! $user->hasPermission('vehicles.dc-approve')) {
            logger()->warning('approveInApp forbidden - not division chief', ['user_id' => $user->id ?? null]);
            return back()->with('error', 'You are not authorized to approve this request.');
        }

        // Allow if assigned chief OR if the acting user is the chief of the requester's division
        $canAct = false;
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id === (int) $user->id) {
            $canAct = true;
        } else {
            $requester = $vehicleRequest->requester;
            if ($requester && $requester->division_id) {
                $isChiefOfRequesterDivision = \App\Models\Division::where('id', $requester->division_id)
                    ->where('division_chief_id', $user->id)
                    ->exists();
                if ($isChiefOfRequesterDivision) $canAct = true;
            }
        }

        if (! $canAct) {
            logger()->warning('approveInApp forbidden - not assigned nor chief of requester division', ['vehicle_request_id' => $vehicleRequest->id, 'assigned_chief' => $vehicleRequest->division_chief_id ?? null, 'user_id' => $user->id]);
            return back()->with('error', 'You are not authorized to approve this request.');
        }

        if ($vehicleRequest->status === 'Approved') return back()->with('success', 'Already approved');

        $vehicleRequest->status = 'Approved';
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Approved by Division Chief', route('vehicle-requests.index')); }

        $this->snapshots->recordApproval(
            approvable: $vehicleRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'approved',
            approver:   $user,
        );

        try {
            // Notify all GSU Head users (so GSU handles driver assignment)
            $gsuHeads = \App\Models\User::havingRole('GSU Head')->get();
            foreach ($gsuHeads as $gsuHead) {
                if ($gsuHead->email) {
                    try {
                        Mail::to($gsuHead->email)->send(new \App\Mail\VehicleRequestGSUHeadMail($vehicleRequest));
                    } catch (\Throwable $e) {
                        logger()->error('Failed to send GSU Head vehicle request notification (in-app)', ['error' => $e->getMessage(), 'gsu_id' => $gsuHead->id]);
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify GSU Head users after in-app approval', ['error' => $e->getMessage()]);
        }

        $this->performSign($request, VehicleRequest::class, $vehicleRequest->id,
            'dc_approval',
            "Vehicle Request #{$vehicleRequest->id}",
            VehicleRequest::class . $vehicleRequest->id . 'dc_approval'
        );

        return back()->with('success', 'Vehicle request approved.');
    }

    public function declineInApp(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();
        logger()->info('declineInApp called', ['user_id' => $user->id ?? null, 'role' => $user->getRoleName(), 'vehicle_request_id' => $vehicleRequest->id]);

        if (! $user || ! $user->hasPermission('vehicles.dc-approve')) {
            logger()->warning('declineInApp forbidden - not division chief', ['user_id' => $user->id ?? null]);
            return back()->with('error', 'You are not authorized to decline this request.');
        }

        $canAct = false;
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id === (int) $user->id) {
            $canAct = true;
        } else {
            $requester = $vehicleRequest->requester;
            if ($requester && $requester->division_id) {
                $isChiefOfRequesterDivision = \App\Models\Division::where('id', $requester->division_id)
                    ->where('division_chief_id', $user->id)
                    ->exists();
                if ($isChiefOfRequesterDivision) $canAct = true;
            }
        }

        if (! $canAct) {
            logger()->warning('declineInApp forbidden - not assigned nor chief of requester division', ['vehicle_request_id' => $vehicleRequest->id, 'assigned_chief' => $vehicleRequest->division_chief_id ?? null, 'user_id' => $user->id]);
            return back()->with('error', 'You are not authorized to decline this request.');
        }

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        if (in_array($vehicleRequest->status, ['Approved','Declined'])) return back()->with('success', 'Already processed');

        $vehicleRequest->status = 'Declined';
        $vehicleRequest->decline_reason = $data['reason'];
        $vehicleRequest->declined_at = now();
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Rejected by Division Chief', route('vehicle-requests.index')); }

        $this->snapshots->recordApproval(
            approvable: $vehicleRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'rejected',
            approver:   $user,
        );

        try {
                $requester = $vehicleRequest->requester;
            $requesterEmail = $requester?->email ?? null;
            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', $vehicleRequest->decline_reason ?? null));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send vehicle request declined notification', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Vehicle request declined.');
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
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Approved by Division Chief', route('vehicle-requests.index')); }

        // Notify all GSU Head users (Division Chief approved -> GSU assigns driver)
        $gsuHeads = \App\Models\User::havingRole('GSU Head')->get();
        foreach ($gsuHeads as $gsuHead) {
            if ($gsuHead->email) {
                try {
                    \Mail::to($gsuHead->email)->send(new \App\Mail\VehicleRequestGSUHeadMail($vehicleRequest));
                } catch (\Throwable $e) {
                    \Log::error('Failed to send GSU Head vehicle request notification', ['error' => $e->getMessage()]);
                }
            }
        }

        // OCD notification is sent only after GSU Head assigns a driver.
        // Previously we notified OCD users here immediately after Division Chief approval.
        // That behavior was changed so OCD receives notification only after driver assignment by GSU Head.

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
     * OCD approve via signed link
     */
    public function approveByOCD(Request $request, VehicleRequest $vehicleRequest, $ocd)
    {
        // Signed route ensures integrity. No further role check here, as it's validated by signature.
        if ($vehicleRequest->status === 'OCD Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        $vehicleRequest->status = 'OCD Approved';
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Fully Approved — request scheduled', route('vehicle-requests.index')); }

        // Notify requester
        $requester = $vehicleRequest->requester;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'OCD Approved', null, 'Office of the Campus Director'));
            } catch (\Throwable $e) {
                \Log::error('Failed to send vehicle request OCD approved notification', ['error' => $e->getMessage()]);
            }
        }

        return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => false]);
    }

    public function showOcdDeclineForm(Request $request, VehicleRequest $vehicleRequest, $ocd)
    {
        // If already approved, show approved page
        if ($vehicleRequest->status === 'Approved' || $vehicleRequest->status === 'OCD Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        $postAction = route('vehicle-requests.ocd.decline.submit', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocd])
            . '?' . $request->getQueryString();

        return view('vehicle_request_decline', ['vehicleRequest' => $vehicleRequest, 'postAction' => $postAction]);
    }

    public function submitOcdDecline(Request $request, VehicleRequest $vehicleRequest, $ocd)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($vehicleRequest->status, ['Approved','Declined','OCD Approved'])) {
            $reason = $vehicleRequest->decline_reason ?? '—';
            return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $reason]);
        }

        $vehicleRequest->status = 'Declined';
        $vehicleRequest->decline_reason = $request->input('reason');
        $vehicleRequest->declined_at = now();
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Declined by OCD', route('vehicle-requests.index')); }

        // Notify requester
        $requester = $vehicleRequest->requester;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', $vehicleRequest->decline_reason, 'Office of the Campus Director'));
            } catch (\Throwable $e) {
                \Log::error('Failed to send vehicle request declined notification', ['error' => $e->getMessage()]);
            }
        }

        return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $vehicleRequest->decline_reason]);
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
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Rejected by Division Chief', route('vehicle-requests.index')); }

        // Notify requester via email (declined by Division Chief)
        $requester = $vehicleRequest->requester;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', $vehicleRequest->decline_reason, 'Division Chief'));
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
        $isAdmin = $request->user()->isSuperAdmin();
        if (! $isAdmin) {
            abort(403);
        }


        $request->validate([
            'purpose' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'date_needed' => 'required|array|min:1',
            'date_needed.*' => 'date',
            'time_of_departure' => 'required|date_format:H:i',
            'eta' => 'required|date_format:H:i',
            'vehicle_type' => 'required|string|max:255',
            'passengers' => 'required|integer|min:1',
            'status' => 'nullable|string|max:255',
            'division_chief_id' => 'required|exists:users,id',
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
        $isAdmin = auth()->user()->isSuperAdmin();
        if (! $isAdmin) {
            abort(403);
        }
        $vehicleRequest->delete();
        return redirect()->route('vehicle-requests.index');
    }

    /**
     * Return JSON bookings for calendar display.
     * Includes both 'Approved' and 'OCD Approved' requests.
     */
    public function bookings(Request $request)
    {
        $rows = VehicleRequest::whereIn('status', ['Approved', 'OCD Approved'])
            ->with(['user'])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $dates = [];
            if ($r->date_needed_multiple && is_array($r->date_needed_multiple) && count($r->date_needed_multiple) > 0) {
                $dates = $r->date_needed_multiple;
            } elseif ($r->date_needed) {
                $dates = [$r->date_needed];
            }
            foreach ($dates as $d) {
                $out[] = [
                    'id' => $r->id,
                    'vehicle_name' => $r->vehicle_type,
                    'plate_no' => optional(\App\Models\Vehicle::where('name', $r->vehicle_type)->first())->plate_no ?? null,
                    'date' => $d,
                    'start_time' => $r->time_of_departure,
                    'end_time' => $r->eta,
                    'purpose' => $r->purpose,
                    'status' => $r->status,
                ];
            }
        }

        return response()->json($out);
    }

    /**
     * Show a printable trip ticket (HTML) for a vehicle request.
     * Only accessible to Admin and GSU Head via route middleware.
     */
    public function printTicket(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();

        if (! $user->hasPermission('vehicles.manage')) {
            abort(403);
        }

        // Allow printing once OCD has approved (includes Completed requests)
        if (! in_array($vehicleRequest->status, ['OCD Approved', 'Completed'])) {
            abort(403, 'Request not ready for printing');
        }

        $vehicleRequest->load(['requester','driver', 'divisionChief']);

        $director    = User::havingRole('OCD')->first();
        $directorSig = $this->sigDataUri($director?->electronic_signature);

        $sigs = $this->loadSigsForPrint(VehicleRequest::class, $vehicleRequest->id);

        return view('vehicle_requests.print_ticket', [
            'request'     => $vehicleRequest,
            'director'    => $director,
            'directorSig' => $directorSig,
            'sigs'        => $sigs,
        ]);
    }

    private function sigDataUri(?string $storedPath): ?string
    {
        if (! $storedPath || $storedPath === '0') {
            return null;
        }

        $ext  = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };

        foreach (['s3', 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($storedPath)) {
                    $contents = Storage::disk($disk)->get($storedPath);
                    if ($contents) {
                        return 'data:' . $mime . ';base64,' . base64_encode($contents);
                    }
                }
            } catch (\Throwable $e) {
                logger()->warning("VehicleRequestController: sig load failed on {$disk}", [
                    'path' => $storedPath, 'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /* =====================================================
     | DIVISION CHIEF IN-APP APPROVAL DASHBOARD
     |=====================================================*/
    public function divisionChiefApproval(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermission('vehicles.dc-approve')) {
            abort(403);
        }

        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = VehicleRequest::with('requester:id,name')
            ->where('status', 'Pending')
            ->where('division_chief_id', $user->id)
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('purpose',     'like', "%{$search}%")
                      ->orWhere('destination', 'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('VehicleRequests/DivisionChiefApproval', [
            'requests'     => $requests,
            'filters'      => ['search' => $search],
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    /* =====================================================
     | OCD IN-APP APPROVAL DASHBOARD
     |=====================================================*/
    public function ocdApproval(Request $request)
    {
        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = VehicleRequest::with('requester:id,name')
            ->where('status', 'Approved')
            ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                $inner->where('purpose',     'like', "%{$search}%")
                      ->orWhere('destination','like', "%{$search}%")
                      ->orWhereHas('requester', fn($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('VehicleRequests/OCDApproval', [
            'requests'     => $requests,
            'filters'      => ['search' => $search],
            'hasPin'       => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function approveByOCDInApp(Request $request, VehicleRequest $vehicleRequest)
    {
        $request->validate(['action' => 'required|in:approve,reject']);

        if ($request->action === 'approve') {
            $vehicleRequest->update(['status' => 'OCD Approved']);
            if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Fully Approved — request scheduled', route('vehicle-requests.index')); }

            $this->snapshots->recordApproval(
                approvable: $vehicleRequest,
                step:       ApprovalStep::REQ_OCD,
                sequence:   4,
                action:     'approved',
                approver:   $request->user(),
            );

            $this->performSign($request, VehicleRequest::class, $vehicleRequest->id,
                'ocd_approval',
                "Vehicle Request #{$vehicleRequest->id}",
                VehicleRequest::class . $vehicleRequest->id . 'ocd_approval'
            );

            try {
                $requester = $vehicleRequest->requester;
                if ($requester?->email) {
                    \Mail::to($requester->email)->send(
                        new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'OCD Approved', null, $request->user()->name)
                    );
                }
            } catch (\Throwable $e) {
                logger()->error('Vehicle OCD approved email failed', ['error' => $e->getMessage()]);
            }
        } else {
            $vehicleRequest->update(['status' => 'Declined', 'decline_reason' => 'Declined by OCD.']);
            if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Declined by OCD', route('vehicle-requests.index')); }

            $this->snapshots->recordApproval(
                approvable: $vehicleRequest,
                step:       ApprovalStep::REQ_OCD,
                sequence:   4,
                action:     'rejected',
                approver:   $request->user(),
            );

            try {
                $requester = $vehicleRequest->requester;
                if ($requester?->email) {
                    \Mail::to($requester->email)->send(
                        new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', 'Declined by OCD.', $request->user()->name)
                    );
                }
            } catch (\Throwable $e) {
                logger()->error('Vehicle OCD declined email failed', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'OCD action recorded.');
    }

    public function signCompletion(Request $request, VehicleRequest $vehicleRequest)
    {
        if ($vehicleRequest->requestor_id !== $request->user()->id) {
            abort(403);
        }

        $this->performSign(
            $request,
            VehicleRequest::class,
            $vehicleRequest->id,
            'completion',
            "Vehicle Request #{$vehicleRequest->id}",
            VehicleRequest::class . $vehicleRequest->id . 'completion'
        );

        return response()->json(['ok' => true]);
    }
}
