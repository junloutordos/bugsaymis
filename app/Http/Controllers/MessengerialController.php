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
use Illuminate\Support\Facades\DB;

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
            $data['requestor'] = $user->name;
            $data['email'] = $user->email;
            $data['unit'] = $user->division->division_name ?? $user->office ?? null;
        }

        $data['status'] = 'Pending';

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

        return view('messengerial_request_declined', ['messengerialRequest' => $messengerialRequest, 'reason' => $messengerialRequest->decline_reason]);
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

        // Restrict updates to model fillable attributes to prevent mass-assignment
        $messengerialRequest->update($request->only($messengerialRequest->getFillable()));
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

        // Allow if Administrator or Records, otherwise allow the original requester
        if (! in_array($role, ['Administrator', 'Records'])) {
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

        return view('messengerial.print_ticket', [
            'request' => $messengerialRequest,
            'divisionChiefName' => $divisionChiefName,
            'requestorSignature' => $requestorSignature,
        ]);
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

        // Determine if courier fields are applicable
        $usesCourier = is_array($messengerialRequest->delivery_methods) && in_array('Courier Services', $messengerialRequest->delivery_methods);

        $rules = [
            'proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'rfsf_reference_no' => 'nullable|string|max:255',
            'date_received_by_courier' => 'nullable|date',
            'date_delivered' => 'nullable|date',
            'proof_remarks' => 'nullable|string|max:2000',
        ];

        if ($usesCourier) {
            $rules['courier_service_provider'] = 'required|string|max:255';
            $rules['courier_cost'] = 'nullable|numeric';
        } else {
            $rules['courier_service_provider'] = 'nullable|string|max:255';
            $rules['courier_cost'] = 'nullable|numeric';
        }

        $validated = $request->validate($rules);

        $file = $request->file('proof');
        $path = $file->store('messengerial_proofs', 'public');

        $messengerialRequest->proof_of_delivery = $path;
        $messengerialRequest->status = 'Completed';
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
}
