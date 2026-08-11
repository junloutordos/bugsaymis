<?php

namespace App\Http\Controllers\HumanResource;

use App\Http\Controllers\Controller;
use App\Http\Traits\SignsDocuments;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\GatePassCreatedMail;
use App\Mail\GatePassDeclinedMail;
use App\Mail\GatePassStatusMail;
use App\Models\User;
use App\Services\NotificationService;
use Inertia\Inertia;

class GatePassController extends Controller
{
    use SignsDocuments;

    public function __construct(private DigitalSignatureService $sigService) {}

    public function index(Request $request)
    {
        // If current user is the chief of a division, show only pending gatepasses
        // for employees in that division. If user is Staff or Faculty, show only
        // gatepasses created by that user. Otherwise show all gatepasses.
        $rows = null;
        $chiefDivision = null;
        $user = $request->user();
        $role = $user?->getRoleName() ?? '';

        if ($user) {
            $chiefDivision = DB::table('divisions')->where('division_chief_id', $user->id)->first();
        }

        if ($chiefDivision) {
            // Pending gatepasses from their division (for approval)
            $divisionRows = DB::table('gatepass')
                ->join('users', 'users.badge_id', '=', 'gatepass.badgeNumber')
                ->where('users.division_id', $chiefDivision->id)
                ->where('gatepass.status', 'Pending')
                ->select('gatepass.*', 'users.name as name', 'users.position as position')
                ->orderByDesc('gatepass.id')
                ->get();

            // Also include the Division Chief's own gatepasses
            $badge = $user->badge_id ?? null;
            $ownRows = collect([]);
            if ($badge !== null) {
                $ownRows = DB::table('gatepass')
                    ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
                    ->whereRaw("CAST(gatepass.badgeNumber AS CHAR) = ?", [(string) $badge])
                    ->select('gatepass.*', 'users.name as name', 'users.position as position')
                    ->orderByDesc('gatepass.id')
                    ->get();
            }

            $rows = $divisionRows->merge($ownRows)->unique('id')->sortByDesc('id')->values();
        } elseif ($user && in_array($role, ['Staff', 'Faculty'])) {
            // Gatepass table stores the user's badge as `badgeNumber` (unsignedInteger).
            // Use the user's `badge_id` (from users table) to filter and include
            // the user's name/position by joining the users table so the Name and
            // Status columns are present in the returned rows.
            $badge = $user->badge_id ?? null;
            if ($badge !== null) {
                // Use CAST to CHAR to avoid type-mismatch issues between stored badge
                // types. This ensures matching works whether `users.badge_id` is
                // stored as string or integer.
                $rows = DB::table('gatepass')
                    ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
                    ->whereRaw("CAST(gatepass.badgeNumber AS CHAR) = ?", [(string) $badge])
                    ->select('gatepass.*', 'users.name as name', 'users.position as position')
                    ->orderByDesc('gatepass.id')
                    ->get();
            } else {
                // No badge associated with user; don't return any rows.
                $rows = collect([]);
            }
        } else {
            // For administrators and other roles, include the requestor's name/position
            // by left-joining the users table on badge id -> badgeNumber. Use CAST to
            // char to avoid type mismatch between stored badge types.
            $rows = DB::table('gatepass')
                ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
                ->select('gatepass.*', 'users.name as name', 'users.position as position')
                ->orderByDesc('gatepass.id')
                ->get();
        }

        $divisionChief = null;
        if ($request->user() && $request->user()->division_id) {
            $division = DB::table('divisions')->where('id', $request->user()->division_id)->first();
            if ($division && !empty($division->division_chief_id)) {
                $chief = DB::table('users')->where('id', $division->division_chief_id)->first();
                if ($chief) {
                    $divisionChief = [
                        'id' => $chief->id,
                        'name' => $chief->name,
                        'position' => $chief->position ?? 'Division Chief',
                    ];
                }
            }
        }

        // also resolve the campus director (division named 'Office of the Campus Director')
        $director = null;
        $officeDivision = DB::table('divisions')->where('division_name', 'Office of the Campus Director')->first();
        if ($officeDivision && !empty($officeDivision->division_chief_id)) {
            $d = DB::table('users')->where('id', $officeDivision->division_chief_id)->first();
            if ($d) {
                $director = [
                    'id' => $d->id,
                    'name' => $d->name,
                    'position' => $d->position ?? 'Director',
                ];
            }
        }

        $user         = $request->user();
        $hasPin       = ! empty($user?->signature_pin);
        $signatureUri = $user ? $this->sigService->getSignatureDataUri($user) : null;

        return Inertia::render('HumanResource/GatePass/Index', [
            'rows'         => $rows,
            'divisionChief'=> $divisionChief,
            'director'     => $director,
            'hasPin'       => $hasPin,
            'signatureUri' => $signatureUri,
        ]);
    }

    public function store(Request $request)
    {
        $input = $request->only([
            'controlno','badgeNumber','badgeID','gatepass_type','gatepass_timeout','gatepass_timein','gatepass_date','gatepass_datefiled','destination','purpose','date_time_approved','actual_timeout','actual_timein','time_consumed'
        ]);

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'controlno' => 'nullable|string|max:50',
            'badgeNumber' => 'nullable|integer',
            'badgeID' => 'nullable|integer',
            'gatepass_type' => 'nullable|string|max:100',
            'gatepass_timeout' => 'nullable|string|max:50',
            'gatepass_timein' => 'nullable|string|max:50',
            // Gate passes may only be filed for today or a future date — never
            // a past date (backdating is not allowed).
            'gatepass_date' => 'nullable|date|after_or_equal:today',
            'gatepass_datefiled' => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:1000',
            'date_time_approved' => 'nullable|string|max:100',
            'actual_timeout' => 'nullable|string|max:100',
            'actual_timein' => 'nullable|string|max:100',
            'time_consumed' => 'nullable|string|max:50',
        ], [
            'gatepass_date.after_or_equal' => 'Gate pass date cannot be in the past. Please select today or a future date.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Auto-generate controlno if not provided, following legacy pattern YEAR-MON-####
        if (empty($data['controlno'])) {
            $last = DB::table('gatepass')->select('controlno')->orderByDesc('id')->value('controlno');
            $year = now()->format('Y');
            $mon = now()->format('m');

            if (!$last) {
                $seq = 1;
            } else {
                $parts = explode('-', $last);
                $prevYear = $parts[0] ?? null;
                $prevSeq = isset($parts[2]) ? intval($parts[2]) : 0;
                if ($year != $prevYear) {
                    $seq = 1;
                } else {
                    $seq = $prevSeq + 1;
                }
            }

            // ensure uniqueness in case of race
            do {
                $rowid = sprintf('%04d', $seq);
                $candidate = $year . '-' . $mon . '-' . $rowid;
                $exists = DB::table('gatepass')->where('controlno', $candidate)->exists();
                if ($exists) $seq++;
            } while ($exists);

            $data['controlno'] = $candidate;
        }

        // Normalize and prepare insert data to match migration columns.
        $insert = [];

        // controlno guaranteed to exist from earlier generation
        $insert['controlno'] = $data['controlno'] ?? '';

        // badgeNumber: use current authenticated user's badge_id from users table when available
        if ($request->user()) {
            $badgeNumber = $request->user()->badge_id ?? $request->user()->badgeNumber ?? $request->user()->badgeID ?? null;
        } else {
            // fallback to provided badgeID if user not authenticated (unlikely)
            $badgeNumber = $data['badgeID'] ?? null;
        }

        // gatepass.badgeNumber is NOT NULL — fail with a clear message instead of
        // a raw DB constraint violation when the requester has no badge on file.
        if ($badgeNumber === null) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['badgeNumber' => ['Your account has no Badge ID on file. Please contact HR/GSU to have one assigned before filing a Gate Pass.']],
            ], 422);
        }

        $insert['badgeNumber'] = $badgeNumber;

        // other fields - ensure keys exist (DB columns are non-nullable in migration)
        $insert['gatepass_type'] = $data['gatepass_type'] ?? '';
        $insert['gatepass_timeout'] = $data['gatepass_timeout'] ?? '';
        $insert['gatepass_timein'] = $data['gatepass_timein'] ?? '';
        $insert['gatepass_date'] = $data['gatepass_date'] ?? '';
        $insert['destination'] = $data['destination'] ?? '';
        $insert['purpose'] = $data['purpose'] ?? '';
        $insert['date_time_approved'] = $data['date_time_approved'] ?? '';
        $insert['actual_timeout'] = $data['actual_timeout'] ?? '';
        $insert['actual_timein'] = $data['actual_timein'] ?? '';
        $insert['time_consumed'] = $data['time_consumed'] ?? '';

        // Determine whether to bypass Division Chief approval:
        // - Division Chief's own gatepass goes directly to OCD (no self-approval)
        // - Staff from the Office of the Campus Director (OCD division) also go directly to OCD
        $submitter        = $request->user();
        $submitterDivision = ($submitter && $submitter->division_id)
            ? DB::table('divisions')->where('id', $submitter->division_id)->first()
            : null;
        $isOCDDivision = $submitterDivision &&
            strtolower(trim($submitterDivision->division_name)) === strtolower('Office of the Campus Director');
        $bypassDivisionChief = $submitter && (
            $submitter->hasRole('DivisionChief')
            || $isOCDDivision
            || \App\Models\Division::where('division_chief_id', $submitter->id)->exists()
        );

        // Status is always computed server-side — never trust client-supplied status.
        $insert['status'] = $bypassDivisionChief ? 'Division Approved' : 'Pending';

        $id = DB::table('gatepass')->insertGetId(array_merge($insert, ['created_at' => now(), 'updated_at' => now()]));

        // Fetch the created gatepass and include the requestor's name (if available)
        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();

        // Notification routing: bypass cases go straight to OCD; others go to their Division Chief.
        try {
            if ($bypassDivisionChief) {
                $ocdUsers = \App\Models\User::havingRole('OCD')->get();
                foreach ($ocdUsers as $ocd) {
                    if (!empty($ocd->email)) {
                        $approveUrl = URL::signedRoute('gatepass.ocd.approve', ['id' => $id, 'ocd' => $ocd->id], now()->addDays(7));
                        $declineUrl = URL::signedRoute('gatepass.ocd.decline', ['id' => $id, 'ocd' => $ocd->id], now()->addDays(7));
                        Mail::to($ocd->email)->send(new GatePassCreatedMail($row, $approveUrl, $declineUrl));
                    }
                }
            } elseif ($submitter && $submitter->division_id) {
                // Regular staff/faculty → notify their Division Chief
                $division = $submitterDivision; // already resolved above
                if ($division && !empty($division->division_chief_id)) {
                    $chief = DB::table('users')->where('id', $division->division_chief_id)->first();
                    if ($chief && !empty($chief->email)) {
                        $approveUrl = URL::signedRoute('gatepass.approve', ['id' => $id, 'chief' => $chief->id], now()->addDays(7));
                        $declineUrl = URL::signedRoute('gatepass.decline', ['id' => $id, 'chief' => $chief->id], now()->addDays(7));
                        Mail::to($chief->email)->send(new GatePassCreatedMail($row, $approveUrl, $declineUrl));
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send gatepass notification', ['error' => $e->getMessage()]);
            report($e);
        }

        $this->performSign($request, 'App\Models\GatePass', (int) $id,
            'submission',
            "Gate Pass #{$id}",
            'GatePass' . $id . 'submission'
        );

        return response()->json(['row' => $row], 201);
    }

    public function update(Request $request, $id)
    {
        $input = $request->only([
            'controlno','badgeID','gatepass_type','gatepass_timeout','gatepass_timein','gatepass_date','gatepass_datefiled','destination','purpose','date_time_approved','actual_timeout','actual_timein','time_consumed','status','decline_reason','date_time_declined'
        ]);

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'controlno' => 'nullable|string|max:50',
            'badgeID' => 'nullable|integer',
            'gatepass_type' => 'nullable|string|max:100',
            'gatepass_timeout' => 'nullable|string|max:50',
            'gatepass_timein' => 'nullable|string|max:50',
            // Gate passes may only be edited to today or a future date — never
            // backdated, even while still Pending.
            'gatepass_date' => 'nullable|date|after_or_equal:today',
            'gatepass_datefiled' => 'nullable|string|max:50',
            'destination' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:1000',
            'date_time_approved' => 'nullable|string|max:100',
            'date_time_declined' => 'nullable|string|max:100',
            'decline_reason' => 'nullable|string|max:1000',
            'actual_timeout' => 'nullable|string|max:100',
            'actual_timein' => 'nullable|string|max:100',
            'time_consumed' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ], [
            'gatepass_date.after_or_equal' => 'Gate pass date cannot be in the past. Please select today or a future date.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Prevent DivisionChief (or other approvers) from changing the requestor's badgeNumber.
        // Fetch existing row to preserve fields not provided.
        $existing = DB::table('gatepass')->where('id', $id)->first();
        if (!$existing) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Status transitions are only allowed through dedicated approval endpoints.
        // Non-approver roles (regular staff filing updates) must never set status directly.
        $user = $request->user();
        $isDC    = $user?->hasAnyRole(['DivisionChief', 'Administrator']) ?? false;
        $isOCD   = $user?->hasAnyRole(['OCD', 'Administrator']) ?? false;
        $isApprover = $isDC || $isOCD;

        if (! $isApprover) {
            // Strip status entirely — regular users can only update form fields
            unset($data['status']);
        } elseif ($isDC && ! $isOCD) {
            // Division Chief may only set the two allowed transition values
            if (isset($data['status']) && $data['status'] === 'Division Declined') {
                $data['status'] = 'Division Declined';
            } else {
                $data['status'] = 'Division Approved';
            }
            if (isset($data['badgeNumber'])) {
                unset($data['badgeNumber']);
            }
        }

        // If the Division Chief provided a decline reason, save it in the
        // `remarks` column so the reason is preserved on the record.
        if (isset($data['status']) && $data['status'] === 'Division Declined') {
            if (!empty($data['decline_reason'])) {
                $data['remarks'] = $data['decline_reason'];
            }
        }
        if (isset($data['decline_reason'])) unset($data['decline_reason']);

        // remove any incoming badgeID to avoid attempting to update a non-existent column
        if (isset($data['badgeID'])) unset($data['badgeID']);

        // Only keep keys that correspond to actual DB columns to avoid SQL errors
        // when migrations (e.g., adding `remarks`) haven't been applied yet.
        $availableColumns = Schema::getColumnListing('gatepass');
        $filteredData = array_intersect_key($data, array_flip($availableColumns));

        // Only update columns provided in $filteredData; preserve others by merging with existing values.
        $update = array_merge((array) $existing, $filteredData, ['updated_at' => now()]);
        // Remove id from update payload if present
        if (isset($update['id'])) unset($update['id']);

        DB::table('gatepass')->where('id', $id)->update($update);
        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();

        // When actual times are recorded or corrected, re-apply the DTR deduction.
        // recompute() derives the deduction live from this table, so it's safe to
        // call again on a later correction — not just the first recording.
        $actualTimeoutChanged = trim($existing->actual_timeout ?? '') !== trim($update['actual_timeout'] ?? '');
        $actualTimeinChanged  = trim($existing->actual_timein  ?? '') !== trim($update['actual_timein']  ?? '');
        $nowActualTimein      = ! empty(trim($update['actual_timein'] ?? ''));
        $nowActualTimeout     = ! empty(trim($update['actual_timeout'] ?? ''));

        if (($actualTimeoutChanged || $actualTimeinChanged) && $nowActualTimein && $nowActualTimeout) {
            try {
                $gateDate = \Carbon\Carbon::parse($existing->gatepass_date)->toDateString();
                $user     = \App\Models\User::whereRaw("CAST(badge_id AS CHAR) = ?", [(string) $existing->badgeNumber])->first();
                if ($user && $gateDate) {
                    app(\App\Services\HR\DTRService::class)->applyGatepassToDate($user->id, $gateDate);
                }
            } catch (\Throwable $e) {
                logger()->warning('Failed to apply gate pass deduction to DTR', [
                    'gatepass_id' => $id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        // If Division Chief declined via in-app update, notify requester by email
        try {
            if (isset($data['status']) && $data['status'] === 'Division Declined') {
                $requester = DB::table('users')->whereRaw("CAST(badge_id AS CHAR) = ?", [(string) $row->badgeNumber])->first();
                $reason = $update['remarks'] ?? $row->remarks ?? null;
                if ($requester && ! empty($requester->email)) {
                    Mail::to($requester->email)->send(new GatePassDeclinedMail($row, $reason));
                }
            }
            // If Division Chief approved via in-app update, notify role_id=7 users
            if (isset($data['status']) && $data['status'] === 'Division Approved') {
                $this->performSign($request, 'App\Models\GatePass', (int) $id,
                    'dc_approval',
                    "Gate Pass #{$id}",
                    'GatePass' . $id . 'dc_approval'
                );

                try {
                    $notifyUsers = DB::table('users')->where('role_id', 7)->get();
                    foreach ($notifyUsers as $u) {
                        if (!empty($u->email)) {
                            try {
                                $approveUrl = URL::signedRoute('gatepass.ocd.approve', ['id' => $id, 'ocd' => $u->id], now()->addDays(7));
                                $declineUrl = URL::signedRoute('gatepass.ocd.decline', ['id' => $id, 'ocd' => $u->id], now()->addDays(7));
                                Mail::to($u->email)->send(new GatePassStatusMail($row, 'Division Approved', null, 'Division Chief', $approveUrl, $declineUrl));
                            } catch (\Throwable $e) {
                                logger()->error('Failed to send gatepass notification to role_id=7 (in-app)', ['error' => $e->getMessage(), 'user_id' => $u->id]);
                                report($e);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    logger()->error('Failed to notify role_id=7 users after division approval (in-app)', ['error' => $e->getMessage()]);
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send gatepass declined notification (in-app)', ['error' => $e->getMessage(), 'id' => $id]);
            report($e);
        }

        return response()->json(['row' => $row]);
    }

    public function destroy($id)
    {
        DB::table('gatepass')->where('id', $id)->delete();
        return response()->json(['deleted' => true]);
    }

    /**
     * Render a printable gate pass as a Blade view.
     */
    public function printView(Request $request, $id)
    {
        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();

        if (!$row) {
            abort(404);
        }

        // resolve division chief from the requester's division, not the viewer's
        $divisionChief = null;
        $gpRequester = DB::table('users')->whereRaw("CAST(badge_id AS CHAR) = ?", [(string) $row->badgeNumber])->first();
        if ($gpRequester && $gpRequester->division_id) {
            $gpDivision = DB::table('divisions')->where('id', $gpRequester->division_id)->first();
            if ($gpDivision && !empty($gpDivision->division_chief_id)) {
                $chief = DB::table('users')->where('id', $gpDivision->division_chief_id)->first();
                if ($chief) {
                    $divisionChief = [
                        'id'       => $chief->id,
                        'name'     => $chief->name,
                        'position' => $chief->position ?? 'Division Chief',
                    ];
                }
            }
        }

        // campus director
        $director = null;
        $officeDivision = DB::table('divisions')->where('division_name', 'Office of the Campus Director')->first();
        if ($officeDivision && !empty($officeDivision->division_chief_id)) {
            $d = DB::table('users')->where('id', $officeDivision->division_chief_id)->first();
            if ($d) {
                $director = [
                    'id' => $d->id,
                    'name' => $d->name,
                    'position' => $d->position ?? 'Director',
                ];
            }
        }

        $sigs = $this->loadSigsForPrint('App\Models\GatePass', (int) $id);

        $verifyUrl = route('document.verify.doc', ['type' => 'gatepass', 'id' => (int) $id]);
        $qrSvg     = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->margin(1)->generate($verifyUrl));

        return view('hr.print_gatepass', [
            'row'           => $row,
            'divisionChief' => $divisionChief,
            'director'      => $director,
            'sigs'          => $sigs,
            'qrSvg'         => $qrSvg,
            'verifyUrl'     => $verifyUrl,
        ]);
    }

    /**
     * Approve gatepass by Division Chief via signed link
     */
    public function approveByDivisionChief(Request $request, $id, $chief)
    {
        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();
        if (! $row) abort(404);

        // verify chief matches requester's division chief if available
        $requester = DB::table('users')->whereRaw("CAST(badge_id AS CHAR) = ?", [(string) $row->badgeNumber])->first();
        if ($requester && $requester->division_id) {
            $division = DB::table('divisions')->where('id', $requester->division_id)->first();
            if ($division && ! empty($division->division_chief_id) && (int) $division->division_chief_id !== (int) $chief) {
                abort(403);
            }
        }

        if ($row->status === 'Division Approved') {
            return view('gatepass_approved', ['gatepass' => $row, 'already' => true]);
        }

        DB::table('gatepass')->where('id', $id)->update(['status' => 'Division Approved', 'date_time_approved' => now(), 'updated_at' => now()]);
        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();

        // Notify users with role_id = 7 (e.g., GSU Head) about the approved gatepass
        try {
            $notifyUsers = DB::table('users')->where('role_id', 7)->get();
            foreach ($notifyUsers as $u) {
                if (!empty($u->email)) {
                    try {
                        $approveUrl = URL::signedRoute('gatepass.ocd.approve', ['id' => $id, 'ocd' => $u->id], now()->addDays(7));
                        $declineUrl = URL::signedRoute('gatepass.ocd.decline', ['id' => $id, 'ocd' => $u->id], now()->addDays(7));
                        Mail::to($u->email)->send(new GatePassStatusMail($row, 'Division Approved', null, 'Division Chief', $approveUrl, $declineUrl));
                    } catch (\Throwable $e) {
                        logger()->error('Failed to send gatepass notification to role_id=7', ['error' => $e->getMessage(), 'user_id' => $u->id]);
                        report($e);
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify role_id=7 users after division approval', ['error' => $e->getMessage()]);
        }

        return view('gatepass_approved', ['gatepass' => $row, 'already' => false]);
    }

    /**
     * Show decline form for signed decline link
     */
    public function showDeclineForm(Request $request, $id, $chief)
    {
        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();
        if (! $row) abort(404);

        if ($row->status === 'Division Approved') {
            return view('gatepass_approved', ['gatepass' => $row, 'already' => true]);
        }

        $postAction = route('gatepass.decline.submit', ['id' => $id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('gatepass_decline', ['gatepass' => $row, 'postAction' => $postAction]);
    }

    /**
     * Handle decline submitted from signed decline form
     */
    public function submitDecline(Request $request, $id, $chief)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();
        if (! $row) abort(404);

        if (in_array($row->status, ['Division Approved','Division Declined'])) {
            $reason = $row->remarks ?? '—';
            return view('gatepass_declined', ['gatepass' => $row, 'reason' => $reason]);
        }

        $updatePayload = [
            'status' => 'Division Declined',
            'remarks' => $request->input('reason'),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('gatepass', 'date_time_declined')) {
            $updatePayload['date_time_declined'] = now();
        }

        DB::table('gatepass')->where('id', $id)->update($updatePayload);

        $row = DB::table('gatepass')->where('id', $id)->first();

        // Attempt to notify requester by email (best-effort) using a Mailable
        try {
            $requester = DB::table('users')->whereRaw("CAST(badge_id AS CHAR) = ?", [(string) $row->badgeNumber])->first();
            if ($requester && ! empty($requester->email)) {
                Mail::to($requester->email)->send(new GatePassDeclinedMail($row, $request->input('reason')));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify gatepass requester after decline', ['error' => $e->getMessage(), 'id' => $id]);
            report($e);
        }

        return view('gatepass_declined', ['gatepass' => $row, 'reason' => $request->input('reason')]);
    }

    /**
     * OCD approve via signed link
     */
    public function approveByOCD(Request $request, $id, $ocd)
    {
        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();
        if (! $row) abort(404);

        if ($row->status === 'OCD Approved') {
            return view('gatepass_approved', ['gatepass' => $row, 'already' => true]);
        }

        DB::table('gatepass')->where('id', $id)->update(['status' => 'OCD Approved', 'updated_at' => now()]);

        $ocdUser = User::find($ocd);
        if ($ocdUser) {
            try {
                $this->sigService->sign(
                    signer:        $ocdUser,
                    signableType:  'App\Models\GatePass',
                    signableId:    (int) $id,
                    documentTitle: "Gate Pass #{$id}",
                    contentToHash: 'GatePass' . $id . 'ocd_approval',
                    metadata:      ['stage' => 'ocd_approval'],
                );
            } catch (\Throwable $e) {
                logger()->error('Failed to record OCD signature on gate pass', ['error' => $e->getMessage(), 'id' => $id]);
            }
        }

        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();

        try {
            $requester = DB::table('users')->whereRaw("CAST(badge_id AS CHAR) = ?", [(string) $row->badgeNumber])->first();
            if ($requester && !empty($requester->email)) {
                Mail::to($requester->email)->send(new GatePassStatusMail($row, 'OCD Approved', null, 'Office of the Campus Director'));
            }
            if ($requester) {
                $user = User::find($requester->id);
                if ($user) {
                    NotificationService::notifyUser($user, 'Gate Pass', $row->controlno, 'Approved by OCD', route('gatepass.index'));
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send OCD-approved notification to requester', ['error' => $e->getMessage(), 'id' => $id]);
            report($e);
        }

        return view('gatepass_approved', ['gatepass' => $row, 'already' => false]);
    }

    public function showOcdDeclineForm(Request $request, $id, $ocd)
    {
        $row = DB::table('gatepass')->where('id', $id)->first();
        if (! $row) abort(404);

        if ($row->status === 'OCD Approved') {
            return view('gatepass_approved', ['gatepass' => $row, 'already' => true]);
        }

        $postAction = route('gatepass.ocd.decline.submit', ['id' => $id, 'ocd' => $ocd]) . '?' . $request->getQueryString();
        return view('gatepass_decline', ['gatepass' => $row, 'postAction' => $postAction]);
    }

    public function submitOcdDecline(Request $request, $id, $ocd)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $row = DB::table('gatepass')->where('id', $id)->first();
        if (! $row) abort(404);

        if (in_array($row->status, ['OCD Approved','OCD Declined'])) {
            $reason = $row->remarks ?? '—';
            return view('gatepass_declined', ['gatepass' => $row, 'reason' => $reason]);
        }

        $updatePayload = ['status' => 'OCD Declined', 'remarks' => $request->input('reason'), 'updated_at' => now()];
        if (Schema::hasColumn('gatepass', 'date_time_declined')) $updatePayload['date_time_declined'] = now();
        DB::table('gatepass')->where('id', $id)->update($updatePayload);

        $row = DB::table('gatepass')
            ->leftJoin('users', DB::raw("CAST(users.badge_id AS CHAR)"), '=', DB::raw("CAST(gatepass.badgeNumber AS CHAR)"))
            ->select('gatepass.*', 'users.name as name', 'users.position as position')
            ->where('gatepass.id', $id)
            ->first();

        try {
            $requester = DB::table('users')->whereRaw("CAST(badge_id AS CHAR) = ?", [(string) $row->badgeNumber])->first();
            if ($requester && !empty($requester->email)) {
                Mail::to($requester->email)->send(new GatePassStatusMail($row, 'OCD Declined', $request->input('reason'), 'Office of the Campus Director'));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify requester after OCD decline', ['error' => $e->getMessage(), 'id' => $id]);
            report($e);
        }

        return view('gatepass_declined', ['gatepass' => $row, 'reason' => $request->input('reason')]);
    }

    /* =====================================================
     | OCD IN-APP APPROVAL DASHBOARD
     |=====================================================*/
    public function ocdApproval(Request $request)
    {
        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $query = DB::table('gatepass')
            ->join('users', 'users.badge_id', '=', 'gatepass.badgeNumber')
            ->where('gatepass.status', 'Division Approved')
            ->select('gatepass.*', 'users.name as requester_name', 'users.email as requester_email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gatepass.purpose', 'like', "%{$search}%")
                  ->orWhere('users.name',     'like', "%{$search}%");
            });
        }

        $requests = $query->latest('gatepass.created_at')->paginate($perPage)->withQueryString();

        $user = $request->user();

        return Inertia::render('HumanResource/GatePass/OCDApproval', [
            'requests'      => $requests,
            'filters'       => ['search' => $search],
            'has_pin'       => ! empty($user?->signature_pin),
            'signature_uri' => $user ? $this->sigService->getSignatureDataUri($user) : null,
        ]);
    }

    public function approveByOCDInApp(Request $request, $id)
    {
        $request->validate(['action' => 'required|in:approve,reject']);

        $row = DB::table('gatepass')->where('id', $id)->first();
        if (! $row) abort(404);

        $newStatus = $request->action === 'approve' ? 'OCD Approved' : 'OCD Declined';
        DB::table('gatepass')->where('id', $id)->update(['status' => $newStatus, 'updated_at' => now()]);

        if ($request->action === 'approve') {
            $this->performSign($request, 'App\Models\GatePass', (int) $id,
                'ocd_approval',
                "Gate Pass #{$id}",
                'GatePass' . $id . 'ocd_approval'
            );
        }

        try {
            $requester = \App\Models\User::where('badge_id', $row->badgeNumber)->first();
            if ($requester?->email) {
                Mail::to($requester->email)->send(new GatePassStatusMail($row, $newStatus, null, $request->user()->name));
            }
        } catch (\Throwable $e) {
            logger()->error('GatePass OCD in-app action email failed', ['error' => $e->getMessage()]);
            report($e);
        }

        return back()->with('success', 'OCD action recorded.');
    }
}
