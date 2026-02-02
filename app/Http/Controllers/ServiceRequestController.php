<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\ServiceRequestCreatedMail;
use App\Mail\ServiceRequestStatusMail;
use App\Models\Division;
use App\Models\User;

class ServiceRequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = ServiceRequest::latest();

        // If the logged-in user is a Staff, show only their own requests
        try {
            $roleName = $user?->role?->name ?? null;
        } catch (\Throwable $e) {
            $roleName = null;
        }

        if (in_array($roleName, ['Staff', 'Faculty']) && $user) {
            $query->where('requestor_id', $user->id);
        }

        $requests = $query->paginate(15);

        return Inertia::render('ServiceRequests/Index', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_type' => 'required|string',
            'copies' => 'nullable|integer|min:1',
            'sheets_per_set' => 'nullable|integer|min:1',
            'date_needed' => 'required|date',
            'time_needed' => 'nullable',
            'purposes' => 'nullable|string',
            'details' => 'nullable|string',
        ]);

        if ($data['service_type'] === 'Reproduction') {
            if (empty($data['copies']) || empty($data['sheets_per_set'])) {
                return redirect()->back()->withErrors(['copies' => 'Copies and sheets per set are required for reproduction requests.'])->withInput();
            }
        }

        $user = $request->user();
        $data['requestor_id'] = $user->id ?? null;
        $data['unit'] = $user?->division?->division_name ?? $user?->office ?? null;

        // set additional metadata
        $data['status'] = 'Pending';

        $sr = ServiceRequest::create($data);
        $requestorName = $user?->name;
        $requestorEmail = $user?->email;

        // find division chief similar to facility flow
        $chiefEmail = null;
        $chiefUser = null;
        if ($user && method_exists($user, 'division') && $user->division) {
            $dc = $user->division->divisionchief;
            if ($dc && $dc->email) {
                $chiefEmail = $dc->email;
                $chiefUser = $dc;
            }
        }

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
                $sr->division_chief_id = $chiefUser->id;
                $sr->save();
            }
            try {
                $approveUrl = $chiefUser ? URL::signedRoute('service-requests.approve', ['serviceRequest' => $sr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : route('service-requests.index');
                $declineUrl = $chiefUser ? URL::signedRoute('service-requests.decline', ['serviceRequest' => $sr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : null;
                Mail::to($chiefEmail)->send(new ServiceRequestCreatedMail($sr, $approveUrl, $declineUrl, $requestorName, $requestorEmail));
            } catch (\Throwable $e) {
                logger()->error('Failed to send service request email', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('service-requests.index')->with('success', 'Service request submitted.');
    }

    public function update(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate([
            'service_type' => 'required|string',
            'copies' => 'nullable|integer|min:1',
            'sheets_per_set' => 'nullable|integer|min:1',
            'date_needed' => 'required|date',
            'time_needed' => 'nullable',
            'purposes' => 'nullable|string',
            'details' => 'nullable|string',
        ]);

        if ($data['service_type'] === 'Reproduction') {
            if (empty($data['copies']) || empty($data['sheets_per_set'])) {
                return redirect()->back()->withErrors(['copies' => 'Copies and sheets per set are required for reproduction requests.'])->withInput();
            }
        }

        $serviceRequest->update($data);

        return redirect()->route('service-requests.index')->with('success', 'Service request updated.');
    }

    public function destroy(ServiceRequest $serviceRequest)
    {
        $serviceRequest->delete();
        return redirect()->route('service-requests.index')->with('success', 'Service request deleted.');
    }

    public function approveByDivisionChief(Request $request, ServiceRequest $serviceRequest, $chief)
    {
        if ($serviceRequest->division_chief_id && (int) $serviceRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }
        if ($serviceRequest->status === 'Approved') {
            return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => true]);
        }

        $serviceRequest->status = 'Approved';
        $serviceRequest->save();

        // Notify requester via email
        try {
            $requester = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
            $requesterEmail = $requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($serviceRequest->division_chief_id) {
                $u = User::find($serviceRequest->division_chief_id);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                $reqName = $requester?->name ?? null;
                $reqEmail = $requester?->email ?? null;
                Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'Approved', null, $approverName, $reqName, $reqEmail));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send service request approved notification', ['error' => $e->getMessage()]);
        }

        // Notify GSU Head users with signed approve/decline links
        try {
            $gsuUsers = User::whereHas('role', function($q) { $q->where('name', 'GSU Head'); })->get();
            foreach ($gsuUsers as $gsuUser) {
                if ($gsuUser->email) {
                    try {
                        $approveUrl = URL::signedRoute('service-requests.gsu.approve', ['serviceRequest' => $serviceRequest->id, 'gsu' => $gsuUser->id], now()->addDays(7));
                        $declineUrl = URL::signedRoute('service-requests.gsu.decline', ['serviceRequest' => $serviceRequest->id, 'gsu' => $gsuUser->id], now()->addDays(7));
                        // provide requestor info to the mail view
                        $reqUser = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
                        $reqName = $reqUser?->name ?? null;
                        $reqEmail = $reqUser?->email ?? null;
                        Mail::to($gsuUser->email)->send(new ServiceRequestCreatedMail($serviceRequest, $approveUrl, $declineUrl, $reqName, $reqEmail));
                    } catch (\Throwable $e) {
                        logger()->error('Failed to send service request GSU notification', ['error' => $e->getMessage(), 'email' => $gsuUser->email]);
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue GSU notifications for service request', ['error' => $e->getMessage()]);
        }

        return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => false]);
    }

    public function showDeclineForm(Request $request, ServiceRequest $serviceRequest, $chief)
    {
        if ($serviceRequest->division_chief_id && (int) $serviceRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        if ($serviceRequest->status === 'Approved') {
            return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => true]);
        }

        $postAction = route('service-requests.decline.submit', ['serviceRequest' => $serviceRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('service_request_decline', ['serviceRequest' => $serviceRequest, 'postAction' => $postAction]);
    }

    public function submitDecline(Request $request, ServiceRequest $serviceRequest, $chief)
    {
        if ($serviceRequest->division_chief_id && (int) $serviceRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($serviceRequest->status, ['Approved','Declined'])) {
            $reason = $serviceRequest->decline_reason ?? '—';
            return view('service_request_declined', ['serviceRequest' => $serviceRequest, 'reason' => $reason]);
        }

        $serviceRequest->status = 'Declined';
        $serviceRequest->decline_reason = $request->input('reason');
        $serviceRequest->declined_at = now();
        $serviceRequest->save();

        // Notify requester via email
        try {
            $requester = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
            $requesterEmail = $requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($serviceRequest->division_chief_id) {
                $u = User::find($serviceRequest->division_chief_id);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                $reqName = $requester?->name ?? null;
                $reqEmail = $requester?->email ?? null;
                Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'Declined', $serviceRequest->decline_reason ?? null, $approverName, $reqName, $reqEmail));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send service request declined notification', ['error' => $e->getMessage()]);
        }

        return view('service_request_declined', ['serviceRequest' => $serviceRequest, 'reason' => $serviceRequest->decline_reason]);
    }

    public function approveByGSU(Request $request, ServiceRequest $serviceRequest, $gsu)
    {
        if ($serviceRequest->status === 'GSU Approved') {
            return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => true]);
        }

        $serviceRequest->status = 'GSU Approved';
        $serviceRequest->save();

        // Notify requester
        try {
            $requester = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
            $requesterEmail = $requester?->email ?? null;
            $approverName = null;
            if ($gsu) {
                $u = User::find($gsu);
                $approverName = $u?->name ?? 'GSU Head';
            } else {
                $approverName = 'GSU Head';
            }

            if ($requesterEmail) {
                $reqName = $requester?->name ?? null;
                $reqEmail = $requester?->email ?? null;
                Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'GSU Approved', null, $approverName, $reqName, $reqEmail));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send service request GSU approved notification', ['error' => $e->getMessage()]);
        }

        return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => false]);
    }

    public function printTicket(Request $request, ServiceRequest $serviceRequest)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';

        if (! in_array($role, ['Administrator', 'GSU Head'])) {
            abort(403);
        }

        $st = strtolower($serviceRequest->status ?? '');
        if (! str_contains($st, 'approved')) {
            abort(403, 'Request not ready for printing');
        }

        return view('service_requests.print_ticket', ['request' => $serviceRequest]);
    }
}
