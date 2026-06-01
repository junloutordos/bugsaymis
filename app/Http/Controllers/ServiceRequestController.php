<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\ServiceRequestCreatedMail;
use App\Mail\ServiceRequestStatusMail;
use App\Models\Division;
use App\Models\User;
use App\Enums\ApprovalStep;
use App\Services\SnapshotService;
use App\Services\DigitalSignatureService;
use App\Http\Traits\SignsDocuments;

class ServiceRequestController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private SnapshotService         $snapshots,
        private DigitalSignatureService $sigService,
    ) {}
    public function index()
    {
        $user = Auth::user();
        // eager-load requester so frontend can display requester name
        $query = ServiceRequest::with('requester')->latest();

        $canViewAll = $user->hasAnyRole(['Administrator', 'GSU Head', 'DivisionChief'])
            || str_contains($user->position ?? '', 'FAD');

        if (! $canViewAll) {
            $query->where('requestor_id', $user->id);
        }

        $requests = $query->get();

        $isDivisionChief = $user->hasRole('DivisionChief');

        return Inertia::render('ServiceRequests/Index', [
            'requests'        => $requests,
            'isDivisionChief' => $isDivisionChief,
            'canViewAll'      => $canViewAll,
            'hasPin'          => ! empty($user->signature_pin),
            'signatureUri'    => $this->sigService->getSignatureDataUri($user),
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
        // do not persist unit on service requests anymore; derive from requester when needed

        // set additional metadata
        $data['status'] = 'Pending';

        $sr = ServiceRequest::create($data);
        $unitLocal = $user?->division?->division_name ?? $user?->office ?? null;
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
            try {
                $approveUrl = $chiefUser ? URL::signedRoute('service-requests.approve', ['serviceRequest' => $sr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : route('service-requests.index');
                $declineUrl = $chiefUser ? URL::signedRoute('service-requests.decline', ['serviceRequest' => $sr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : null;
                Mail::to($chiefEmail)->send(new ServiceRequestCreatedMail($sr, $approveUrl, $declineUrl, $requestorName, $requestorEmail));
            } catch (\Throwable $e) {
                logger()->error('Failed to send service request email', ['error' => $e->getMessage()]);
            }
        }

        $this->performSign($request, ServiceRequest::class, $sr->id,
            'submission',
            "Service Request #{$sr->id}",
            ServiceRequest::class . $sr->id . 'submission'
        );

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
        $assignedChiefId = $this->resolveDivisionChiefId($serviceRequest);
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $chief) {
            abort(403);
        }
        if ($serviceRequest->status === 'Approved') {
            return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => true]);
        }

        $serviceRequest->status = 'Approved';
        $serviceRequest->save();
        if ($serviceRequest->requester) { NotificationService::notifyUser($serviceRequest->requester, 'Service Request', "#{$serviceRequest->id}", 'Approved by Division Chief', route('service-requests.index')); }

        // Notify requester via email
        try {
            $requester = $serviceRequest->requester ?? ($serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null);
            $requesterEmail = $requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($assignedChiefId) {
                $u = User::find($assignedChiefId);
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
            $gsuUsers = User::havingRole('GSU Head')->get();
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

        // Notify FAD Chief users for next-level approval/decline
        try {
            $fadUsers = User::select('id','email','position')
                        ->where('position', 'like', '%FAD%')
                        ->get();
            foreach ($fadUsers as $fad) {
                if (! $fad->email) continue;
                try {
                    $approveUrl = URL::signedRoute('service-requests.fad.approve', ['serviceRequest' => $serviceRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('service-requests.fad.decline', ['serviceRequest' => $serviceRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    Mail::to($fad->email)->send(new \App\Mail\ServiceRequestFADMail($serviceRequest, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    logger()->error('Failed to send service request FAD notification', ['error' => $e->getMessage(), 'email' => $fad->email]);
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue FAD notifications for service request', ['error' => $e->getMessage()]);
        }

        return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => false]);
    }

    // Authenticated in-app approval by logged-in Division Chief
    public function approveInApp(Request $request, ServiceRequest $serviceRequest)
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('facilities.dc-approve')) abort(403);

        // Validate that the division chief is responsible for this request
        $assignedChiefId = $this->resolveDivisionChiefId($serviceRequest);
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $user->id) {
            abort(403);
        }
        if ($serviceRequest->status === 'Approved') {
            return back()->with('success', 'Already approved');
        }

        $serviceRequest->status = 'Approved';
        $serviceRequest->save();

        $this->snapshots->recordApproval(
            approvable: $serviceRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'approved',
            approver:   $user,
        );

        // Notify requester via email (reuse existing logic)
        try {
            $requester = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
            $requesterEmail = $requester?->email ?? null;
            $approverName = $user->name ?? null;
            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'Approved', null, $approverName, $requester?->name ?? null, $requesterEmail));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send service request approved notification', ['error' => $e->getMessage()]);
        }

        // Notify FAD Chief users for next-level approval/decline
        try {
            $fadUsers = User::select('id','email','position')
                        ->where('position', 'like', '%FAD%')
                        ->get();
            foreach ($fadUsers as $fad) {
                if (! $fad->email) continue;
                try {
                    $approveUrl = URL::signedRoute('service-requests.fad.approve', ['serviceRequest' => $serviceRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('service-requests.fad.decline', ['serviceRequest' => $serviceRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    Mail::to($fad->email)->send(new \App\Mail\ServiceRequestFADMail($serviceRequest, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    logger()->error('Failed to send service request FAD notification (in-app)', ['error' => $e->getMessage(), 'email' => $fad->email]);
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue FAD notifications for service request (in-app)', ['error' => $e->getMessage()]);
        }

        $this->performSign($request, ServiceRequest::class, $serviceRequest->id,
            'dc_approval',
            "Service Request #{$serviceRequest->id}",
            ServiceRequest::class . $serviceRequest->id . 'dc_approval'
        );

        return back()->with('success', 'Service request approved.');
    }

    public function declineInApp(Request $request, ServiceRequest $serviceRequest)
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('facilities.dc-approve')) abort(403);
        $assignedChiefId = $this->resolveDivisionChiefId($serviceRequest);
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $user->id) {
            abort(403);
        }

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        if (in_array($serviceRequest->status, ['Approved','Declined'])) {
            return back()->with('success', 'Already processed');
        }

        $serviceRequest->status = 'Declined';
        $serviceRequest->decline_reason = $data['reason'];
        $serviceRequest->declined_at = now();
        $serviceRequest->save();

        $this->snapshots->recordApproval(
            approvable: $serviceRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'rejected',
            approver:   $user,
        );

        try {
            $requester = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
            $requesterEmail = $requester?->email ?? null;
            $approverName = $user->name ?? null;
            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'Declined', $serviceRequest->decline_reason ?? null, $approverName, $requester?->name ?? null, $requesterEmail));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send service request declined notification', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Service request declined.');
    }

    public function showDeclineForm(Request $request, ServiceRequest $serviceRequest, $chief)
    {
        $assignedChiefId = $this->resolveDivisionChiefId($serviceRequest);
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $chief) {
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
        $assignedChiefId = $this->resolveDivisionChiefId($serviceRequest);
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $chief) {
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
            $requester = $serviceRequest->requester ?? ($serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null);
            $requesterEmail = $requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($assignedChiefId) {
                $u = User::find($assignedChiefId);
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

        /**
         * Approve service request by FAD Chief via signed link
         */
        public function approveByFAD(Request $request, ServiceRequest $serviceRequest, $chief)
        {
            if ($serviceRequest->status === 'FAD Approved') {
                return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => true]);
            }

            $serviceRequest->status = 'FAD Approved';
            $serviceRequest->save();

            // Notify requester that FAD approved
            try {
                $requesterEmail = $serviceRequest->requester?->email ?? null;
                $approverName = null;
                if ($chief) {
                    $u = User::find($chief);
                    $approverName = $u?->name ?? 'FAD Chief';
                } else {
                    $approverName = 'FAD Chief';
                }

                if ($requesterEmail) {
                    Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'FAD Approved', null, $approverName));
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to send service request FAD approved notification', ['error' => $e->getMessage()]);
            }

            return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => false]);
        }

        public function showFadDeclineForm(Request $request, ServiceRequest $serviceRequest, $chief)
        {
            if ($serviceRequest->status === 'FAD Approved') {
                return view('service_request_approved', ['serviceRequest' => $serviceRequest, 'already' => true]);
            }

            $postAction = route('service-requests.fad.decline.submit', ['serviceRequest' => $serviceRequest->id, 'chief' => $chief])
                . '?' . $request->getQueryString();

            return view('service_request_decline', ['serviceRequest' => $serviceRequest, 'postAction' => $postAction]);
        }

        public function submitFadDecline(Request $request, ServiceRequest $serviceRequest, $chief)
        {
            $request->validate([
                'reason' => 'required|string|max:1000',
            ]);

            if (in_array($serviceRequest->status, ['Approved','Declined','FAD Approved','FAD Declined'])) {
                $reason = $serviceRequest->decline_reason ?? '—';
                return view('service_request_declined', ['serviceRequest' => $serviceRequest, 'reason' => $reason]);
            }

            $serviceRequest->status = 'FAD Declined';
            $serviceRequest->decline_reason = $request->input('reason');
            $serviceRequest->declined_at = now();
            $serviceRequest->save();

            try {
                $requesterEmail = $serviceRequest->requester?->email ?? null;
                $approverName = null;
                if ($chief) {
                    $u = User::find($chief);
                    $approverName = $u?->name ?? 'FAD Chief';
                } else {
                    $approverName = 'FAD Chief';
                }

                if ($requesterEmail) {
                    Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'Declined', $serviceRequest->decline_reason ?? null, $approverName));
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to send service request FAD declined notification', ['error' => $e->getMessage()]);
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

    /* =====================================================
     | DIVISION CHIEF IN-APP APPROVAL DASHBOARD
     |=====================================================*/
    public function divisionChiefApproval(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermission('facilities.dc-approve')) {
            abort(403);
        }

        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $divisionIds = Division::where('division_chief_id', $user->id)->pluck('id');

        $requests = ServiceRequest::with('requester:id,name,division_id')
            ->where('status', 'Pending')
            ->whereHas('requester', fn ($q) => $q->whereIn('division_id', $divisionIds))
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('service_type', 'like', "%{$search}%")
                      ->orWhere('purposes',   'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ServiceRequests/DivisionChiefApproval', [
            'requests' => $requests,
            'filters'  => ['search' => $search],
        ]);
    }

    /* =====================================================
     | FAD IN-APP APPROVAL DASHBOARD
     |=====================================================*/
    public function fadApproval(Request $request)
    {
        $user = $request->user();
        $isFAD = str_contains($user->position ?? '', 'FAD') || $user->hasRole('Administrator');
        if (! $isFAD) abort(403);

        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = ServiceRequest::with('requester:id,name')
            ->where('status', 'Approved')
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('service_type', 'like', "%{$search}%")
                      ->orWhere('purposes',   'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ServiceRequests/FADApproval', [
            'requests' => $requests,
            'filters'  => ['search' => $search],
        ]);
    }

    public function fadAction(Request $request, ServiceRequest $serviceRequest)
    {
        $user = $request->user();
        $isFAD = str_contains($user->position ?? '', 'FAD') || $user->hasRole('Administrator');
        if (! $isFAD) abort(403);

        $request->validate(['action' => 'required|in:approve,reject']);

        if ($request->action === 'approve') {
            $serviceRequest->update(['status' => 'FAD Approved']);
            if ($serviceRequest->requester) { NotificationService::notifyUser($serviceRequest->requester, 'Service Request', "#{$serviceRequest->id}", 'Approved by FAD', route('service-requests.index')); }

            $this->performSign($request, ServiceRequest::class, $serviceRequest->id,
                'fad_approval',
                "Service Request #{$serviceRequest->id}",
                ServiceRequest::class . $serviceRequest->id . 'fad_approval'
            );

            try {
                $requester = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
                $requesterEmail = $requester?->email ?? null;
                if ($requesterEmail) {
                    Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'FAD Approved', null, $user->name, $requester?->name, $requesterEmail));
                }
            } catch (\Throwable $e) {
                logger()->error('Service request FAD approved email failed', ['error' => $e->getMessage()]);
            }
        } else {
            $serviceRequest->update(['status' => 'FAD Declined', 'decline_reason' => 'Declined by FAD Chief.', 'declined_at' => now()]);
            if ($serviceRequest->requester) { NotificationService::notifyUser($serviceRequest->requester, 'Service Request', "#{$serviceRequest->id}", 'Declined by FAD', route('service-requests.index')); }
            try {
                $requester = $serviceRequest->requestor_id ? User::find($serviceRequest->requestor_id) : null;
                $requesterEmail = $requester?->email ?? null;
                if ($requesterEmail) {
                    Mail::to($requesterEmail)->send(new ServiceRequestStatusMail($serviceRequest, 'FAD Declined', 'Declined by FAD Chief.', $user->name, $requester?->name, $requesterEmail));
                }
            } catch (\Throwable $e) {
                logger()->error('Service request FAD declined email failed', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'FAD action recorded.');
    }

    public function printTicket(Request $request, ServiceRequest $serviceRequest)
    {
        $user = $request->user();

        if (! $user->hasPermission('facilities.manage')) {
            abort(403);
        }

        $st = strtolower($serviceRequest->status ?? '');
        if (! str_contains($st, 'approved')) {
            abort(403, 'Request not ready for printing');
        }

        $sigs = $this->loadSigsForPrint(ServiceRequest::class, $serviceRequest->id);
        return view('service_requests.print_ticket', [
            'request' => $serviceRequest,
            'sigs'    => $sigs,
        ]);
    }

    protected function resolveDivisionChiefId(ServiceRequest $serviceRequest)
    {
        $assigned = $serviceRequest->requester?->division?->division_chief_id ?? null;
        if (! $assigned && ! empty($serviceRequest->unit)) {
            $div = Division::where('division_name', $serviceRequest->unit)->first();
            if ($div && $div->division_chief_id) $assigned = $div->division_chief_id;
        }
        return $assigned;
    }
}
