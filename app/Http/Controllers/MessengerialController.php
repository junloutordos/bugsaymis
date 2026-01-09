<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\MessengerialRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Models\Division;
use App\Models\User;
use App\Mail\MessengerialRequestCreatedMail;
use App\Mail\MessengerialRequestStatusMail;
use App\Mail\MessengerialRequestRecordsMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MessengerialController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';
        $canViewAll = in_array($role, ['Administrator', 'Records']);

        $requests = MessengerialRequest::when(!$canViewAll, fn($q) => $q->where('email', $user->email))
            ->latest()
            ->get();

        return Inertia::render('Messengerial/Index', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purpose' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
            'reference_no' => 'nullable|string|max:100',
            'delivery_methods' => 'nullable|array',
            'delivery_methods.*' => 'in:Thru Email,In-person Delivery,Courier Services',
            'messengerial_kinds' => 'nullable|array',
            'messengerial_kinds.*' => 'in:Communication Letter,Package',
            'consignee_name' => 'nullable|string|max:255',
            'consignee_contact' => 'nullable|string|max:50',
            'consignee_email' => 'nullable|email|max:255',
        ]);

        $data = $request->only(['purpose','destination','reference_no','delivery_methods','messengerial_kinds','consignee_name','consignee_contact','consignee_email']);

        $user = $request->user();
        if ($user) {
            $data['requestor'] = $user->name;
            $data['email'] = $user->email;
            $data['unit'] = $user->division->division_name ?? $user->office ?? null;
        }

        $data['status'] = 'Pending';

        $mr = MessengerialRequest::create($data);

        // try to find division chief: prefer relation from authenticated user
        $chiefEmail = null;
        $chiefUser = null;
        if ($user && method_exists($user, 'division') && $user->division) {
            $dc = $user->division->divisionchief;
            if ($dc && $dc->email) {
                $chiefEmail = $dc->email;
                $chiefUser = $dc;
            }
        }

        // fallback: try to find by matching unit name to divisions table
        if (! $chiefEmail && ! empty($data['unit'])) {
            $div = Division::where('division_name', $data['unit'])->first();
            if ($div && $div->division_chief_id) {
                $dcUser = User::find($div->division_chief_id);
                if ($dcUser && $dcUser->email) {
                    $chiefEmail = $dcUser->email;
                    $chiefUser = $dcUser;
                }
            }
        }

        if ($chiefEmail) {
            if ($chiefUser) {
                $mr->division_chief_id = $chiefUser->id;
                $mr->save();
            }

            try {
                $approveUrl = $chiefUser ? URL::signedRoute('messengerial.approve', ['messengerialRequest' => $mr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : route('messengerial.index');
                $declineUrl = $chiefUser ? URL::signedRoute('messengerial.decline', ['messengerialRequest' => $mr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : null;
                Mail::to($chiefEmail)->send(new MessengerialRequestCreatedMail($mr, $approveUrl, $declineUrl));
            } catch (\Throwable $e) {
                logger()->error('Failed to send messengerial request email', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('messengerial.index')->with('success', 'Request submitted');
    }

    /**
     * Approve messengerial request via signed link from Division Chief
     */
    public function approveByDivisionChief(Request $request, MessengerialRequest $messengerialRequest, $chief)
    {
        if ($messengerialRequest->division_chief_id && (int) $messengerialRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        if ($messengerialRequest->status === 'Approved') {
            return view('messengerial_request_approved', ['messengerialRequest' => $messengerialRequest, 'already' => true]);
        }

        $messengerialRequest->status = 'Approved';
        $messengerialRequest->save();

        // Notify requester via email (approved by Division Chief)
        try {
            $requesterEmail = $messengerialRequest->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($messengerialRequest->division_chief_id) {
                $u = User::find($messengerialRequest->division_chief_id);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new MessengerialRequestStatusMail($messengerialRequest, 'Approved', null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send messengerial request approved notification', ['error' => $e->getMessage()]);
        }

        // Notify Records users so they can process the approved request
        try {
            $recordsUsers = User::whereHas('role', function($q) { $q->where('name', 'Records'); })->get();
            $processUrl = url('/messengerial');
            foreach ($recordsUsers as $rUser) {
                if ($rUser->email) {
                    try {
                        Mail::to($rUser->email)->send(new \App\Mail\MessengerialRequestRecordsMail($messengerialRequest, $processUrl));
                    } catch (\Throwable $ee) {
                        logger()->error('Failed to send messengerial records notification', ['error' => $ee->getMessage(), 'email' => $rUser->email]);
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue Records notifications for messengerial request', ['error' => $e->getMessage()]);
        }

        return view('messengerial_request_approved', ['messengerialRequest' => $messengerialRequest, 'already' => false]);
    }

    public function showDeclineForm(Request $request, MessengerialRequest $messengerialRequest, $chief)
    {
        if ($messengerialRequest->division_chief_id && (int) $messengerialRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        if ($messengerialRequest->status === 'Approved') {
            return view('messengerial_request_approved', ['messengerialRequest' => $messengerialRequest, 'already' => true]);
        }

        $postAction = route('messengerial.decline.submit', ['messengerialRequest' => $messengerialRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $messengerialRequest, 'postAction' => $postAction]);
    }

    public function submitDecline(Request $request, MessengerialRequest $messengerialRequest, $chief)
    {
        if ($messengerialRequest->division_chief_id && (int) $messengerialRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($messengerialRequest->status, ['Approved','Declined'])) {
            $reason = $messengerialRequest->decline_reason ?? '—';
            return view('facility_request_declined', ['facilityRequest' => $messengerialRequest, 'reason' => $reason]);
        }

        $messengerialRequest->status = 'Declined';
        $messengerialRequest->decline_reason = $request->input('reason');
        $messengerialRequest->declined_at = now();
        $messengerialRequest->save();

        // Notify requester
        try {
            $requesterEmail = $messengerialRequest->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($messengerialRequest->division_chief_id) {
                $u = User::find($messengerialRequest->division_chief_id);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new MessengerialRequestStatusMail($messengerialRequest, 'Declined', $messengerialRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send messengerial request declined notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_declined', ['facilityRequest' => $messengerialRequest, 'reason' => $messengerialRequest->decline_reason]);
    }

    public function update(Request $request, MessengerialRequest $messengerialRequest)
    {
        $user = $request->user();
        $isAdmin = ($user->role->name ?? '') === 'Administrator';

        // Non-admins may update only when the request is still Pending
        if (! $isAdmin) {
            if ($messengerialRequest->status !== 'Pending') {
                abort(403);
            }
        }

        $messengerialRequest->update($request->all());
        return redirect()->route('messengerial.index');
    }

    public function destroy(MessengerialRequest $messengerialRequest)
    {
        $user = auth()->user();
        $isAdmin = ($user->role->name ?? '') === 'Administrator';

        // Non-admins may delete only when the request is still Pending
        if (! $isAdmin) {
            if ($messengerialRequest->status !== 'Pending') {
                abort(403);
            }
        }

        $messengerialRequest->delete();
        return redirect()->route('messengerial.index');
    }

    /**
     * Show a printable view for a messengerial request.
     * Only accessible to Admin and Records via route middleware.
     */
    public function printTicket(Request $request, MessengerialRequest $messengerialRequest)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';

        if (! in_array($role, ['Administrator', 'Records'])) {
            abort(403);
        }

        return view('messengerial.print_ticket', ['request' => $messengerialRequest]);
    }

    /**
     * Upload proof of delivery (only Records and Administrator)
     */
    public function uploadProof(Request $request, MessengerialRequest $messengerialRequest)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';
        if (! in_array($role, ['Administrator', 'Records'])) {
            abort(403);
        }

        // Only allow upload when request is approved and not already completed
        if ($messengerialRequest->status === 'Completed') {
            return redirect()->back()->with('error', 'Proof already uploaded.');
        }

        $request->validate([
            'proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $file = $request->file('proof');
        $path = $file->store('messengerial_proofs', 'public');

        $messengerialRequest->proof_of_delivery = $path;
        $messengerialRequest->status = 'Completed';
        $messengerialRequest->completed_at = now();
        $messengerialRequest->save();

        // notify requester
        try {
            $requesterEmail = $messengerialRequest->email ?? null;
            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new MessengerialRequestStatusMail($messengerialRequest, 'Completed', null, $user->name ?? null));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send messengerial completed notification', ['error' => $e->getMessage()]);
        }

        return redirect()->route('messengerial.index')->with('success', 'Proof uploaded and request marked completed.');
    }
}
