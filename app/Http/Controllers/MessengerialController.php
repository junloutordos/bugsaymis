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
use Carbon\Carbon;

class MessengerialController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';
        $canViewAll = in_array($role, ['Administrator']);

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
            return view('facility_request_approved', ['facilityRequest' => $messengerialRequest, 'already' => true]);
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

        return view('facility_request_approved', ['facilityRequest' => $messengerialRequest, 'already' => false]);
    }

    public function showDeclineForm(Request $request, MessengerialRequest $messengerialRequest, $chief)
    {
        if ($messengerialRequest->division_chief_id && (int) $messengerialRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        if ($messengerialRequest->status === 'Approved') {
            return view('facility_request_approved', ['facilityRequest' => $messengerialRequest, 'already' => true]);
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
        $isAdmin = ($request->user()->role->name ?? '') === 'Administrator';
        if (! $isAdmin) abort(403);

        $messengerialRequest->update($request->all());
        return redirect()->route('messengerial.index');
    }

    public function destroy(MessengerialRequest $messengerialRequest)
    {
        $isAdmin = (auth()->user()->role->name ?? '') === 'Administrator';
        if (! $isAdmin) abort(403);
        $messengerialRequest->delete();
        return redirect()->route('messengerial.index');
    }
}
