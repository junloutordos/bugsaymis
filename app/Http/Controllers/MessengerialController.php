<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\MessengerialRequest;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Models\Division;
use App\Models\User;
use App\Mail\MessengerialRequestCreatedMail;
use App\Mail\MessengerialRequestStatusMail;
use App\Mail\MessengerialRequestRecordsMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Enums\ApprovalStep;
use App\Services\SnapshotService;
use App\Services\DigitalSignatureService;
use App\Http\Traits\SignsDocuments;

class MessengerialController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private SnapshotService         $snapshots,
        private DigitalSignatureService $sigService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAll = $user->hasPermission('messengerial.manage');

        $requests = MessengerialRequest::when(!$canViewAll, fn($q) => $q->where('email', $user->email))
            ->latest()
            ->get()
            ->map(function ($r) use ($canViewAll) {
                // Flag missing files so the UI can show re-upload button
                $r->proof_missing = $canViewAll
                    && $r->proof_of_delivery
                    && ! Storage::disk('s3')->exists($r->proof_of_delivery);
                return $r;
            });

        $hasPendingCsm = MessengerialRequest::where('user_id', $user->id)
            ->where('status', 'Completed')
            ->exists();

        return Inertia::render('Messengerial/Index', [
            'requests'      => $requests,
            'hasPendingCsm' => $hasPendingCsm,
            'hasPin'        => ! empty($user->signature_pin),
            'signatureUri'  => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purpose' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
            'delivery_methods' => 'nullable|array',
            'delivery_methods.*' => 'in:Hand-Carry,Courier Services',
            'messengerial_kinds' => 'nullable|array',
            'messengerial_kinds.*' => 'in:Letter Envelope,Folder or Brown Envelope,Box Small,Box Medium,Box Large',
            'consignee_name' => 'nullable|string|max:255',
            'consignee_contact' => 'nullable|string|max:50',
            'consignee_email' => 'nullable|email|max:255',
        ]);

        // Do not accept user-provided reference_no; we'll generate it server-side
        $data = $request->only(['purpose','destination','delivery_methods','messengerial_kinds','consignee_name','consignee_contact','consignee_email']);

        $user = $request->user();
        if ($user) {
            $hasPendingCsm = MessengerialRequest::where('user_id', $user->id)
                ->where('status', 'Completed')
                ->exists();

            if ($hasPendingCsm) {
                return back()->withErrors(['purpose' => 'Please rate your completed messengerial request(s) before submitting a new one.']);
            }

            $data['user_id']   = $user->id;
            $data['requestor'] = $user->name;
            $data['email']     = $user->email;
            $data['unit']      = $user->division->division_name ?? $user->office ?? null;
        }

        $data['status'] = 'Pending Division Chief Approval';

        // Determine unit/division for prefix. Prefer stored `acronym` on divisions table.
        $userUnit = null;
        $unitAcronym = null;
        if ($user) {
            $userUnit = $user->division->division_name ?? $user->office ?? null;
            $unitAcronym = $user->division->acronym ?? null;
        }
        $unitName = $data['unit'] ?? $userUnit ?? '';

        $prefix = 'GEN';
        if (!empty($unitAcronym)) {
            $prefix = strtoupper(trim($unitAcronym));
        } else {
            // Fallback: existing heuristic (detect FAD or build acronym from words)
            $unitNorm = strtolower(trim($unitName));
            if ($unitNorm !== '') {
                if (str_contains($unitNorm, 'finance') && str_contains($unitNorm, 'administr')) {
                    $prefix = 'FAD';
                } elseif (strtoupper($unitName) === 'FAD') {
                    $prefix = 'FAD';
                } else {
                    $words = preg_split('/\s+/', $unitName);
                    $abbr = '';
                    foreach ($words as $w) {
                        if (strlen($w) > 0) $abbr .= strtoupper($w[0]);
                    }
                    $prefix = $abbr ?: 'GEN';
                }
            }
        }

        // Generate reference number in transaction-safe way
        $referenceNo = null;
        DB::transaction(function () use (&$referenceNo, $prefix) {
            $year = date('Y');
            $month = date('m');

            $seq = DB::table('messengerial_sequences')
                ->where('division_code', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $seq) {
                // Special starting numbers per acronym
                $specialStarts = [
                    'CID' => 7,
                    'SSD' => 2,
                    'FAD' => 6,
                ];
                $start = $specialStarts[$prefix] ?? 1;
                $number = $start;
                $id = DB::table('messengerial_sequences')->insertGetId([
                    'division_code' => $prefix,
                    'year' => $year,
                    'last_number' => $number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $number = $seq->last_number + 1;
                DB::table('messengerial_sequences')->where('id', $seq->id)->update(['last_number' => $number, 'updated_at' => now()]);
            }

            $referenceNo = sprintf('%s-%s-%s-%04d', $prefix, date('Y'), $month, $number);
        });

        $data['reference_no'] = $referenceNo;

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

        // Override: if requestor is from OCD division, route to OCD user instead of division chief
        if ($user && $this->isOcdDivision($user)) {
            $ocdUser = User::havingRole('OCD')->first();
            if ($ocdUser && $ocdUser->email) {
                $chiefEmail = $ocdUser->email;
                $chiefUser  = $ocdUser;
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

        $this->performSign($request, MessengerialRequest::class, $mr->id,
            'submission',
            "Messengerial Request #{$mr->id}",
            MessengerialRequest::class . $mr->id . 'submission'
        );

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
        if ($messengerialRequest->user) { NotificationService::notifyUser($messengerialRequest->user, 'Messengerial Request', $messengerialRequest->reference_no ?? "#{$messengerialRequest->id}", 'Approved by Division Chief', route('messengerial.index')); }

        $approver = User::find($chief);

        if ($approver) {
            $this->snapshots->recordApproval(
                approvable: $messengerialRequest,
                step:       ApprovalStep::REQ_DIVISION_CHIEF,
                sequence:   1,
                action:     'approved',
                approver:   $approver,
            );
        }

        // Notify requester
        try {
            if ($messengerialRequest->email) {
                Mail::to($messengerialRequest->email)->send(
                    new MessengerialRequestStatusMail($messengerialRequest, 'Approved', null, $approver?->name)
                );
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send messengerial approved notification', ['error' => $e->getMessage()]);
        }

        // Notify Records users
        try {
            $recordsUsers = User::havingRole('Records')->get();
            $processUrl   = url('/messengerial');
            foreach ($recordsUsers as $rUser) {
                if ($rUser->email) {
                    try {
                        Mail::to($rUser->email)->send(new MessengerialRequestRecordsMail($messengerialRequest, $processUrl));
                    } catch (\Throwable $ee) {
                        logger()->error('Failed to send messengerial records notification', ['error' => $ee->getMessage()]);
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue Records notifications', ['error' => $e->getMessage()]);
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

        return view('messengerial_request_decline', ['messengerialRequest' => $messengerialRequest, 'postAction' => $postAction]);
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
            return view('messengerial_request_declined', ['messengerialRequest' => $messengerialRequest, 'reason' => $reason]);
        }

        $messengerialRequest->status = 'Declined';
        if ($messengerialRequest->user) { NotificationService::notifyUser($messengerialRequest->user, 'Messengerial Request', $messengerialRequest->reference_no ?? "#{$messengerialRequest->id}", 'Rejected by Division Chief', route('messengerial.index')); }
        $messengerialRequest->decline_reason = $request->input('reason');
        $messengerialRequest->declined_at = now();
        $messengerialRequest->save();

        if ($approver = User::find($chief)) {
            $this->snapshots->recordApproval(
                approvable: $messengerialRequest,
                step:       ApprovalStep::REQ_DIVISION_CHIEF,
                sequence:   1,
                action:     'rejected',
                approver:   $approver,
            );
        }

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

        return view('messengerial_request_declined', ['messengerialRequest' => $messengerialRequest, 'reason' => $messengerialRequest->decline_reason]);
    }

    /**
     * In-app Division Chief approval page
     */
    public function forApproval(Request $request)
    {
        $user = $request->user();
        if (! $user->hasAnyPermission(['messengerial.dc-approve', 'messengerial.ocd-approve'])) {
            abort(403, 'Unauthorized');
        }

        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $query = MessengerialRequest::where('status', 'Pending Division Chief Approval');
        if ($user->hasRole('DivisionChief') || $user->hasRole('OCD')) {
            // Division Chief sees their own assigned requests;
            // OCD sees requests assigned to them (from OCD division requestors)
            $query->where('division_chief_id', $user->id);
        }

        $requests = $query
            ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                $inner->where('purpose', 'like', "%{$search}%")
                      ->orWhere('requestor', 'like', "%{$search}%")
                      ->orWhere('reference_no', 'like', "%{$search}%")
                      ->orWhere('destination', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Messengerial/ForApprovalMessengerial', [
            'requests'     => $requests,
            'filters'      => ['search' => $search],
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    /**
     * In-app Division Chief approve/reject action
     */
    public function divisionChiefAction(Request $request, MessengerialRequest $messengerialRequest)
    {
        $user = $request->user();
        if (! $user->hasAnyPermission(['messengerial.dc-approve', 'messengerial.ocd-approve'])) {
            abort(403);
        }

        // DC/OCD can only act on requests assigned to them
        if ($user->hasAnyPermission(['messengerial.dc-approve', 'messengerial.ocd-approve']) && ! $user->isSuperAdmin()) {
            if ((int) $messengerialRequest->division_chief_id !== $user->id) {
                abort(403);
            }
        }

        $request->validate(['action' => 'required|in:approve,reject']);

        if ($request->action === 'approve') {
            $messengerialRequest->status = 'Approved';
            $messengerialRequest->save();
            if ($messengerialRequest->user) { NotificationService::notifyUser($messengerialRequest->user, 'Messengerial Request', $messengerialRequest->reference_no ?? "#{$messengerialRequest->id}", 'Approved by Division Chief', route('messengerial.index')); }

            $this->performSign($request, MessengerialRequest::class, $messengerialRequest->id,
                'division_chief',
                "Messengerial Request #{$messengerialRequest->id}",
                MessengerialRequest::class . $messengerialRequest->id . 'division_chief'
            );

            // Notify requester
            try {
                if ($messengerialRequest->email) {
                    Mail::to($messengerialRequest->email)->send(
                        new MessengerialRequestStatusMail($messengerialRequest, 'Approved', null, $user->name)
                    );
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to send messengerial approved notification', ['error' => $e->getMessage()]);
            }

            // Notify Records users
            try {
                $recordsUsers = User::havingRole('Records')->get();
                $processUrl   = url('/messengerial');
                foreach ($recordsUsers as $rUser) {
                    if ($rUser->email) {
                        try {
                            Mail::to($rUser->email)->send(new MessengerialRequestRecordsMail($messengerialRequest, $processUrl));
                        } catch (\Throwable $ee) {
                            logger()->error('Failed to send messengerial records notification', ['error' => $ee->getMessage()]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to queue Records notifications', ['error' => $e->getMessage()]);
            }
        } else {
            $request->validate(['reason' => 'nullable|string|max:1000']);
            $messengerialRequest->status       = 'Declined';
            if ($messengerialRequest->user) { NotificationService::notifyUser($messengerialRequest->user, 'Messengerial Request', $messengerialRequest->reference_no ?? "#{$messengerialRequest->id}", 'Rejected by Division Chief', route('messengerial.index')); }
            $messengerialRequest->decline_reason = $request->input('reason');
            $messengerialRequest->declined_at  = now();
            $messengerialRequest->save();

            try {
                if ($messengerialRequest->email) {
                    Mail::to($messengerialRequest->email)->send(
                        new MessengerialRequestStatusMail($messengerialRequest, 'Declined', $messengerialRequest->decline_reason, $user->name)
                    );
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to send messengerial declined notification', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'Action recorded.');
    }

    public function update(Request $request, MessengerialRequest $messengerialRequest)
    {
        $user = $request->user();
        $isAdmin = $user->isSuperAdmin();

        // Non-admins may update only when the request is still Pending
        if (! $isAdmin) {
            if ($messengerialRequest->status !== 'Pending') {
                abort(403);
            }
        }

        // Restrict updates to model fillable attributes to prevent mass-assignment
        $messengerialRequest->update($request->only($messengerialRequest->getFillable()));
        return redirect()->route('messengerial.index');
    }

    public function destroy(MessengerialRequest $messengerialRequest)
    {
        $user = auth()->user();
        $isAdmin = $user->isSuperAdmin();

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

        // Allow if Administrator or Records, otherwise allow the original requester
        if (! $user->hasPermission('messengerial.manage')) {
            $userEmail = $user->email ?? null;
            $requestorEmail = $messengerialRequest->email ?? null;
            $requestorName = $messengerialRequest->requestor ?? null;

            if (! ($userEmail && $requestorEmail && strtolower($userEmail) === strtolower($requestorEmail))
                && ! ($requestorName && strtolower($requestorName) === strtolower($user->name ?? '')) ) {
                abort(403);
            }
        }

        $divisionChiefName = null;
        if ($messengerialRequest->division_chief_id) {
            $dc = User::find($messengerialRequest->division_chief_id);
            $divisionChiefName = $dc?->name ?? null;
        }

        // Try to find the requestor's user record (to get stored electronic signature)
        $requestorSignature = null;
        if (! empty($messengerialRequest->email)) {
            $reqUser = User::where('email', $messengerialRequest->email)->first();
            $requestorSignature = $reqUser?->electronic_signature ?? null;
        }

        $sigs = $this->loadSigsForPrint(MessengerialRequest::class, $messengerialRequest->id);

        return view('messengerial.print_ticket', [
            'request'            => $messengerialRequest,
            'divisionChiefName'  => $divisionChiefName,
            'requestorSignature' => $requestorSignature,
            'sigs'               => $sigs,
        ]);
    }

    /**
     * Returns true if the given user belongs to the Office of the Campus Director.
     * Matches by OCD role, division acronym, or division name.
     */
    private function isOcdDivision(User $user): bool
    {
        if ($user->hasPermission('messengerial.ocd-approve')) {
            return true;
        }

        $division = $user->division;
        if (! $division) {
            return false;
        }

        if (strtolower(trim($division->acronym ?? '')) === 'ocd') {
            return true;
        }

        return str_contains(strtolower($division->division_name ?? ''), 'campus director');
    }

    /**
     * Upload proof of delivery (only Records and Administrator)
     */
    public function uploadProof(Request $request, MessengerialRequest $messengerialRequest)
    {
        $user = $request->user();
        if (! $user->hasPermission('messengerial.manage')) {
            abort(403);
        }

        // Allow re-upload only if the file is actually missing from S3
        if ($messengerialRequest->status === 'Completed') {
            $existingPath = $messengerialRequest->proof_of_delivery;
            if ($existingPath && Storage::disk('s3')->exists($existingPath)) {
                return back()->with('error', 'Proof already uploaded and verified.');
            }
            // File is missing — allow re-upload to fix it
        }

        $usesCourier = is_array($messengerialRequest->delivery_methods)
            && in_array('Courier Services', $messengerialRequest->delivery_methods);

        $validated = $request->validate([
            'proof_base64'             => ['required', 'string'],
            'proof_name'               => ['nullable', 'string', 'max:255'],
            'rfsf_reference_no'        => ['nullable', 'string', 'max:255'],
            'date_received_by_courier' => ['nullable', 'date'],
            'date_delivered'           => ['nullable', 'date'],
            'proof_remarks'            => ['nullable', 'string', 'max:2000'],
            'courier_service_provider' => [$usesCourier ? 'required' : 'nullable', 'string', 'max:255'],
            'courier_cost'             => ['nullable', 'numeric'],
        ]);

        // Decode base64 data URI sent from the frontend (Cloudflare blocks multipart)
        $dataUri  = $validated['proof_base64'];
        $base64   = str_contains($dataUri, ',') ? explode(',', $dataUri, 2)[1] : $dataUri;
        $content  = base64_decode($base64);

        if ($content === false || strlen($content) === 0) {
            return back()->withErrors(['proof_base64' => 'Invalid file data. Please try again.']);
        }

        $originalName = $validated['proof_name'] ?? 'proof.pdf';
        $safeName     = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $s3Key        = 'messengerial_proofs/' . \Illuminate\Support\Str::random(40) . '_' . $safeName;

        Storage::disk('s3')->put($s3Key, $content);
        $path = $s3Key;

        $messengerialRequest->proof_of_delivery = $path;
        $messengerialRequest->status = 'Completed';
        if ($messengerialRequest->user) { NotificationService::notifyUser($messengerialRequest->user, 'Messengerial Request', $messengerialRequest->reference_no ?? "#{$messengerialRequest->id}", 'Delivery Completed', route('messengerial.index')); }
        $messengerialRequest->completed_at = now();

        // assign additional fields
        $messengerialRequest->rfsf_reference_no = $validated['rfsf_reference_no'] ?? null;
        $messengerialRequest->courier_service_provider = $validated['courier_service_provider'] ?? null;
        $messengerialRequest->courier_cost = $validated['courier_cost'] ?? null;
        if (! empty($validated['date_received_by_courier'])) {
            $messengerialRequest->date_received_by_courier = Carbon::parse($validated['date_received_by_courier']);
        }
        if (! empty($validated['date_delivered'])) {
            $messengerialRequest->date_delivered = Carbon::parse($validated['date_delivered']);
        }
        $messengerialRequest->proof_remarks = $validated['proof_remarks'] ?? null;

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

    public function viewProof(Request $request, MessengerialRequest $messengerialRequest)
    {
        $user = $request->user();
        $isOwner   = $messengerialRequest->user_id === $user->id
                  || $messengerialRequest->email   === $user->email;
        $isStaff   = $user->hasAnyPermission(['messengerial.manage', 'messengerial.dc-approve', 'messengerial.ocd-approve']);

        if (! $isOwner && ! $isStaff) {
            abort(403);
        }

        $path = $messengerialRequest->proof_of_delivery;
        if (! $path) {
            return redirect()->route('messengerial.index')
                ->with('error', 'No proof file is attached to this request.');
        }

        if (! Storage::disk('s3')->exists($path)) {
            return redirect()->route('messengerial.index')
                ->with('error', 'Proof file not found on the server. The file may have failed to upload — please re-upload the proof.');
        }

        $contents = Storage::disk('s3')->get($path);
        $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime     = match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return response($contents, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    public function ocdApproval(Request $request)
    {
        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = MessengerialRequest::where('status', 'Approved')
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('purpose',      'like', "%{$search}%")
                      ->orWhere('requestor',   'like', "%{$search}%")
                      ->orWhere('reference_no','like', "%{$search}%")
                      ->orWhere('destination', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Messengerial/OCDApprovalMessengerial', [
            'requests' => $requests,
            'filters'  => ['search' => $search],
        ]);
    }

    public function ocdAction(Request $request, MessengerialRequest $messengerialRequest)
    {
        $request->validate(['action' => 'required|in:approve,reject']);

        if ($request->action === 'approve') {
            $messengerialRequest->update(['status' => 'OCD Approved']);
        if ($messengerialRequest->user) { NotificationService::notifyUser($messengerialRequest->user, 'Messengerial Request', $messengerialRequest->reference_no ?? "#{$messengerialRequest->id}", 'Approved by OCD', route('messengerial.index')); }
        } else {
            $request->validate(['reason' => 'nullable|string|max:1000']);
            $messengerialRequest->update([
                'status'         => 'Declined',
                'decline_reason' => $request->input('reason') ?? 'Declined by OCD.',
                'declined_at'    => now(),
            ]);
        }

        return back()->with('success', 'OCD action recorded.');
    }
}
