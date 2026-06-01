<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\FacilityRequest;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\FacilityRequestCreatedMail;
use App\Models\Division;
use App\Models\User;
use App\Models\Facility;
use App\Enums\ApprovalStep;
use App\Services\SnapshotService;
use App\Services\DigitalSignatureService;
use App\Http\Traits\SignsDocuments;

class FacilityRequestController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private SnapshotService         $snapshots,
        private DigitalSignatureService $sigService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $canViewAll = $user->hasAnyRole(['Administrator', 'GSU Head', 'DivisionChief', 'OCD'])
            || str_contains($user->position ?? '', 'FAD');

        $requestsQuery = FacilityRequest::latest();

        if (! $canViewAll) {
            $requestsQuery->where('requestor_id', $user->id);
        }

        // eager-load requester and its division so frontend can display requestor name/unit
        $requests = $requestsQuery->with('requester.division')->get();

        // also provide facilities list for venue selection
        $facilities = \App\Models\Facility::orderBy('name')->get(['id','name']);
        $facilityMap = $facilities->pluck('name', 'id')->toArray();
        $requests = $requests->map(function ($r) use ($facilityMap) {
            $arr = $r->venue ?? [];
            if (!is_array($arr)) {
                $arr = $arr ? [$arr] : [];
            }
            $names = array_map(function ($id) use ($facilityMap) {
                return $facilityMap[$id] ?? $id;
            }, $arr);
            $ra = $r->toArray();
            $ra['venue'] = $names;
            return $ra;
        });

        $misUsers = User::havingRole('MIS')->select('id', 'name', 'position')->orderBy('name')->get();

        $isDivisionChief = $user->hasRole('DivisionChief');

        return Inertia::render('FacilityRequests/Index', [
            'requests'     => $requests,
            'facilities'   => $facilities,
            'misUsers'     => $misUsers,
            'isDivisionChief' => $isDivisionChief,
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    public function store(Request $request)
    {
        $minDate = now()->addDays(3)->toDateString();

        $request->validate([
            'unit' => 'nullable|string|max:50',
            'activity' => 'nullable|string|max:100',
            'purpose' => 'nullable|string|max:255',
            'nature' => ['nullable','in:Curricular,Co-Curricular,Others'],
            'nature_other' => 'nullable|string|max:255',
            'participants' => 'nullable|string|max:255',
            'male' => 'nullable|integer|min:0',
            'female' => 'nullable|integer|min:0',
            'venue' => 'nullable|array',
            'venue.*' => 'exists:facilities,id',
            'date_start' => 'nullable|date|after_or_equal:' . $minDate,
            'date_end'   => 'nullable|date|after_or_equal:date_start',
            // toggle to request IT assistance
            'requires_it_assistance' => 'nullable|boolean',
            'assigned_mis_user' => 'required_if:requires_it_assistance,1|nullable|exists:users,id',
            'equipment' => 'nullable|array',
            'equipment.*' => 'in:Chairs,Tables,Microphone,Whiteboard,Projector,Electric Fans,Airconditioner,Trashbins',
            'equipment_quantities' => 'nullable|array',
            'equipment_quantities.*' => 'nullable|integer|min:1',
            'time_start' => 'nullable|date_format:H:i',
            'time_end' => 'nullable|date_format:H:i',
        ], [
            'date_start.after_or_equal' => 'Facility requests must be filed at least 3 days before the event date.',
        ]);

        $data = $request->only([
            'activity','purpose','nature',
            'date_start','date_end','time_start','time_end',
            'male','female','venue','chairs','tables','mic','whiteboard','projector','elecfans','aircon','trashbins','equipment','equipment_quantities',
            'others','remarks','participants','reference_no'
        ]);

        // set requestor and email from authenticated user (do not persist legacy `unit` column)
        $user = $request->user();
        $unit = null;
        if ($user) {
            $data['requestor_id'] = $user->id;
            // resolve unit from user's division name if available (used only for fallback)
            if (method_exists($user, 'division') && $user->division) {
                $unit = $user->division->division_name ?? $user->office ?? null;
            } else {
                $unit = $user->office ?? null;
            }
        }

        // ensure venue is stored as array (casts will json encode)
        if ($request->has('venue')) {
            $data['venue'] = $request->input('venue');
        }

        // ensure equipment_quantities is stored as array when provided
        if ($request->has('equipment_quantities')) {
            $data['equipment_quantities'] = $request->input('equipment_quantities');
        }

        // handle nature 'Others' specification: combine into nature field if provided
        if (($request->input('nature') ?? '') === 'Others') {
            $other = $request->input('nature_other');
            $data['nature'] = $other ? ('Others: ' . $other) : 'Others';
        }

        // conflict detection: do not allow booking if any selected facility
        // is already booked for overlapping date/time (exclude Declined)
        $selected = $data['venue'] ?? [];
        $conflicts = [];
        if (! empty($selected) && $data['date_start']) {
            $newStart = $data['date_start'];
            $newEnd = $data['date_end'] ?? $newStart;
            $newTimeStart = $data['time_start'] ?? null;
            $newTimeEnd = $data['time_end'] ?? null;

            $facilityNames = \App\Models\Facility::whereIn('id', $selected)->pluck('name', 'id')->toArray();

            foreach ($selected as $fid) {
                $query = FacilityRequest::whereJsonContains('venue', $fid)
                    ->where(function ($q) use ($newStart, $newEnd) {
                        $q->where(function ($q2) use ($newStart, $newEnd) {
                            $q2->whereBetween('date_start', [$newStart, $newEnd])
                                ->orWhereBetween('date_end', [$newStart, $newEnd])
                                ->orWhere(function ($q3) use ($newStart, $newEnd) {
                                    $q3->where('date_start', '<=', $newStart)
                                       ->where('date_end', '>=', $newEnd);
                                });
                        });
                    })
                    ->where('status', '!=', 'Declined')
                    ->get();

                foreach ($query as $ex) {
                    // if either existing or new request has no times, treat as conflict
                    if (empty($ex->time_start) || empty($ex->time_end) || empty($newTimeStart) || empty($newTimeEnd)) {
                        $conflicts[$fid] = $facilityNames[$fid] ?? $fid;
                        break;
                    }

                    // compare times
                    $exStart = Carbon::createFromFormat('H:i:s', $ex->time_start)->format('H:i');
                    $exEnd = Carbon::createFromFormat('H:i:s', $ex->time_end)->format('H:i');
                    $nStart = Carbon::createFromFormat('H:i', $newTimeStart)->format('H:i');
                    $nEnd = Carbon::createFromFormat('H:i', $newTimeEnd)->format('H:i');

                    // intervals overlap if start < other_end and end > other_start
                    if ($nStart < $exEnd && $nEnd > $exStart) {
                        $conflicts[$fid] = $facilityNames[$fid] ?? $fid;
                        break;
                    }
                }
            }
        }

        if (! empty($conflicts)) {
            $names = implode(', ', array_unique(array_values($conflicts)));
            return redirect()->back()->withInput()->withErrors(['venue' => "The following facility(ies) are already booked for the selected date/time: $names"]);
        }

        $data['date_filed'] = now();
        $data['status'] = 'Pending';

        $fr = FacilityRequest::create($data);

        // If requester asked for IT assistance, create IT Job Request (once) inside a transaction
        if ($request->boolean('requires_it_assistance')) {
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($fr, $user, $request) {
                    // avoid duplicate IT job creation
                    $exists = \App\Models\ITJobRequest::where('facility_request_id', $fr->id)->exists();
                    if ($exists) return;

                    // determine division chief id for division of requesting user
                    $divisionChiefId = null;
                    if ($user && method_exists($user, 'division') && $user->division && $user->division->divisionchief) {
                        $divisionChiefId = $user->division->divisionchief->id;
                    } else {
                        // fallback: try Division table match
                        $reqUnit = $fr->requester?->division?->division_name ?? $fr->unit ?? null;
                        $div = $reqUnit ? \App\Models\Division::where('division_name', $reqUnit)->first() : null;
                        if ($div && $div->division_chief_id) $divisionChiefId = $div->division_chief_id;
                    }

                    // Build a clearer title combining activity, date(s) and time(s)
                    $titleParts = [];
                    if (! empty($fr->activity)) {
                        $titleParts[] = $fr->activity;
                    }

                    $dateStart = $fr->date_start ? Carbon::parse($fr->date_start)->format('Y-m-d') : null;
                    $dateEnd = $fr->date_end ? Carbon::parse($fr->date_end)->format('Y-m-d') : null;
                    if ($dateStart && $dateEnd) {
                        $dateRange = ($dateStart === $dateEnd) ? $dateStart : ($dateStart . ' to ' . $dateEnd);
                        $titleParts[] = $dateRange;
                    } elseif ($dateStart) {
                        $titleParts[] = $dateStart;
                    }

                    $timeStart = $fr->time_start ? Carbon::parse($fr->time_start)->format('H:i') : null;
                    $timeEnd = $fr->time_end ? Carbon::parse($fr->time_end)->format('H:i') : null;
                    if ($timeStart && $timeEnd) {
                        $titleParts[] = $timeStart . '-' . $timeEnd;
                    } elseif ($timeStart) {
                        $titleParts[] = $timeStart;
                    }

                    // Append venue names if available to provide clearer context
                    if (! empty($fr->venue)) {
                        $venueIds = is_array($fr->venue) ? $fr->venue : [$fr->venue];
                        $venueNames = \App\Models\Facility::whereIn('id', $venueIds)->pluck('name')->toArray();
                        if (! empty($venueNames)) {
                            $titleParts[] = implode('; ', $venueNames);
                        }
                    }

                    $title = ! empty($titleParts) ? implode(', ', $titleParts) : ('Facility Request #' . $fr->id);

                    $itData = [
                        'user_id' => $user?->id ?? null,
                        'facility_request_id' => $fr->id,
                        'category' => 'Technical Assistance on Events',
                        'title' => $title,
                        'description' => 'Auto-created IT job request for Facility Request #' . $fr->id,
                        'status' => 'Pending Division Chief Approval',
                        'divisionchief_id' => $divisionChiefId,
                        'assignedto' => $request->input('assigned_mis_user') ?? null,
                    ];

                    $jobRequest = \App\Models\ITJobRequest::create($itData);

                    // create tracking log
                    \App\Models\ITJRTrackingLog::create([
                        'it_job_request_id' => $jobRequest->id,
                        'status' => 'Submitted IT Job Request',
                        'remarks' => 'Automatically created from Facility Request #' . $fr->id,
                        'updated_by' => $user?->id ?? null,
                    ]);

                    // Send notification to division chief if available
                    if ($divisionChiefId) {
                        $chief = \App\Models\User::find($divisionChiefId);
                        if ($chief && $chief->email) {
                            try {
                                $approveUrl = \Illuminate\Support\Facades\URL::signedRoute('it-job-requests.dc.approve', ['jobRequest' => $jobRequest->id, 'chief' => $chief->id], now()->addDays(7));
                                $declineUrl = \Illuminate\Support\Facades\URL::signedRoute('it-job-requests.dc.decline', ['jobRequest' => $jobRequest->id, 'chief' => $chief->id], now()->addDays(7));
                                \Illuminate\Support\Facades\Mail::to($chief->email)->send(new \App\Mail\DivisionChiefITJRApprovalMail($jobRequest, $approveUrl, $declineUrl));
                            } catch (\Throwable $e) {
                                logger()->error('Failed to send auto-created ITJR Division Chief email', ['error' => $e->getMessage()]);
                            }
                        }
                    }
                });
            } catch (\Throwable $e) {
                logger()->error('Failed to automatically create IT Job Request for Facility Request', ['error' => $e->getMessage(), 'facility_request_id' => $fr->id]);
            }
        }

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
        if (! $chiefEmail && ! empty($unit)) {
            $div = Division::where('division_name', $unit)->first();
            if ($div && $div->division_chief_id) {
                $dcUser = User::find($div->division_chief_id);
                if ($dcUser && $dcUser->email) {
                    $chiefEmail = $dcUser->email;
                    $chiefUser = $dcUser;
                }
            }
        }

        if ($chiefEmail) {
            // Do not persist division_chief_id on the request; it can be resolved via requester->division
            try {
                // create signed approve/decline links (valid 7 days)
                $approveUrl = $chiefUser ? URL::signedRoute('facility-requests.approve', ['facilityRequest' => $fr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : route('facility-requests.index');
                $declineUrl = $chiefUser ? URL::signedRoute('facility-requests.decline', ['facilityRequest' => $fr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : null;
                Mail::to($chiefEmail)->send(new FacilityRequestCreatedMail($fr, $approveUrl, $declineUrl));
            } catch (\Throwable $e) {
                logger()->error('Failed to send facility request email', ['error' => $e->getMessage()]);
            }
        }

        $this->performSign($request, FacilityRequest::class, $fr->id,
            'submission',
            "Facility Request #{$fr->id}",
            FacilityRequest::class . $fr->id . 'submission'
        );

        return redirect()->route('facility-requests.index')->with('success', 'Facility request submitted');
    }

    public function update(Request $request, FacilityRequest $facilityRequest)
    {
        $isAdmin = $request->user()->isSuperAdmin();
        if (! $isAdmin) abort(403);

        $facilityRequest->update($request->all());
        return redirect()->route('facility-requests.index');
    }

    /**
     * Approve facility request via signed link from Division Chief
     * DC approval → Pending FAD Approval (FAD is next)
     */
    public function approveByDivisionChief(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        $assignedChiefId = $facilityRequest->requester?->division?->division_chief_id ?? null;
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $chief) {
            abort(403);
        }

        if (in_array($facilityRequest->status, ['Pending FAD Approval', 'Approved'])) {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $facilityRequest->status = 'Pending FAD Approval';
        $facilityRequest->save();

        // Notify FAD Chief users with signed approve/decline links
        try {
            $fadUsers = \App\Models\User::select('id','email','position')
                        ->where('position', 'like', '%FAD%')
                        ->get();
            foreach ($fadUsers as $fad) {
                if (! $fad->email) continue;
                try {
                    $approveUrl = URL::signedRoute('facility-requests.fad.approve', ['facilityRequest' => $facilityRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('facility-requests.fad.decline', ['facilityRequest' => $facilityRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    \Mail::to($fad->email)->send(new \App\Mail\FacilityRequestFADMail($facilityRequest, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    logger()->error('Failed to send facility request FAD notification', ['error' => $e->getMessage(), 'email' => $fad->email]);
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue FAD notifications for facility request', ['error' => $e->getMessage()]);
        }

        return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => false]);
    }

    // Authenticated in-app approval by logged-in Division Chief
    public function approveInApp(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('facilities.dc-approve')) {
            logger()->warning('Blocked facility approve in-app: role mismatch', ['user_id' => $user?->id, 'role' => $user?->getRoleName(), 'facility_request_id' => $facilityRequest->id]);
            abort(403);
        }

        $assignedChiefId = $facilityRequest->requester?->division?->division_chief_id ?? null;
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $user->id) {
            // allow if user is division chief for the unit of the request (by division name or by membership)
            $myDivisionNames = Division::where('division_chief_id', $user->id)->pluck('division_name')->map(fn($n) => (string)$n)->toArray();
            $myDivisionIds = Division::where('division_chief_id', $user->id)->pluck('id')->toArray();
            $myDivisionEmails = User::whereIn('division_id', $myDivisionIds)->pluck('email')->toArray();

            $frUnit = $facilityRequest->requester?->division?->division_name ?? $facilityRequest->unit ?? null;
            $unitMatches = $frUnit && in_array($frUnit, $myDivisionNames);
            $emailMatches = $facilityRequest->requester?->email && in_array($facilityRequest->requester?->email, $myDivisionEmails);

            if (! $unitMatches && ! $emailMatches) {
                logger()->warning('Blocked facility approve in-app: division chief id mismatch', ['user_id' => $user->id, 'assigned_division_chief_id' => $assignedChiefId, 'facility_request_unit' => $frUnit ?? $facilityRequest->unit, 'facility_request_email' => $facilityRequest->requester?->email, 'facility_request_id' => $facilityRequest->id]);
                abort(403);
            }
        }
        if (in_array($facilityRequest->status, ['Pending FAD Approval', 'Approved'])) {
            return back()->with('success', 'Already forwarded for FAD approval.');
        }

        $facilityRequest->status = 'Pending FAD Approval';
        $facilityRequest->save();

        $this->snapshots->recordApproval(
            approvable: $facilityRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'approved',
            approver:   $user,
        );

        // Notify FAD Chief users for next-level approval/decline
        try {
            $fadUsers = \App\Models\User::select('id','email','position')
                        ->where('position', 'like', '%FAD%')
                        ->get();
            foreach ($fadUsers as $fad) {
                if (! $fad->email) continue;
                try {
                    $approveUrl = URL::signedRoute('facility-requests.fad.approve', ['facilityRequest' => $facilityRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('facility-requests.fad.decline', ['facilityRequest' => $facilityRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    \Mail::to($fad->email)->send(new \App\Mail\FacilityRequestFADMail($facilityRequest, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    logger()->error('Failed to send facility request FAD notification (in-app)', ['error' => $e->getMessage(), 'email' => $fad->email]);
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue FAD notifications for facility request (in-app)', ['error' => $e->getMessage()]);
        }

        $this->performSign($request, FacilityRequest::class, $facilityRequest->id,
            'dc_approval',
            "Facility Request #{$facilityRequest->id}",
            FacilityRequest::class . $facilityRequest->id . 'dc_approval'
        );

        return back()->with('success', 'Facility request approved. FAD has been notified.');
    }

    public function declineInApp(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission('facilities.dc-approve')) {
            logger()->warning('Blocked facility decline in-app: role mismatch', ['user_id' => $user?->id, 'role' => $user?->getRoleName(), 'facility_request_id' => $facilityRequest->id]);
            abort(403);
        }
        $assignedChiefId = $facilityRequest->requester?->division?->division_chief_id ?? null;
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $user->id) {
            $myDivisionNames = Division::where('division_chief_id', $user->id)->pluck('division_name')->map(fn($n) => (string)$n)->toArray();
            $myDivisionIds = Division::where('division_chief_id', $user->id)->pluck('id')->toArray();
            $myDivisionEmails = User::whereIn('division_id', $myDivisionIds)->pluck('email')->toArray();

            $frUnit = $facilityRequest->requester?->division?->division_name ?? $facilityRequest->unit ?? null;
            $unitMatches = $frUnit && in_array($frUnit, $myDivisionNames);
            $emailMatches = $facilityRequest->requester?->email && in_array($facilityRequest->requester?->email, $myDivisionEmails);

            if (! $unitMatches && ! $emailMatches) {
                logger()->warning('Blocked facility decline in-app: division chief id mismatch', ['user_id' => $user->id, 'assigned_division_chief_id' => $assignedChiefId, 'facility_request_unit' => $frUnit ?? $facilityRequest->unit, 'facility_request_email' => $facilityRequest->requester?->email, 'facility_request_id' => $facilityRequest->id]);
                abort(403);
            }
        }

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        if (in_array($facilityRequest->status, ['Approved','Declined'])) return back()->with('success', 'Already processed');

        $facilityRequest->status = 'Declined';
        $facilityRequest->decline_reason = $data['reason'];
        $facilityRequest->declined_at = now();
        $facilityRequest->save();

        $this->snapshots->recordApproval(
            approvable: $facilityRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'rejected',
            approver:   $user,
        );

        try {
            $requesterEmail = $facilityRequest->requester?->email ?? null;
            $approverName = $user->name ?? null;
            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Declined', $facilityRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request declined notification', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Facility request declined.');
    }

    public function showDeclineForm(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        $assignedChiefId = $facilityRequest->requester?->division?->division_chief_id ?? null;
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $chief) {
            abort(403);
        }

        if (in_array($facilityRequest->status, ['Pending FAD Approval', 'Approved'])) {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $postAction = route('facility-requests.decline.submit', ['facilityRequest' => $facilityRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $facilityRequest, 'postAction' => $postAction]);
    }

    public function submitDecline(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        $assignedChiefId = $facilityRequest->requester?->division?->division_chief_id ?? null;
        if ($assignedChiefId && (int) $assignedChiefId !== (int) $chief) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($facilityRequest->status, ['Pending FAD Approval', 'Approved', 'Declined'])) {
            $reason = $facilityRequest->decline_reason ?? '—';
            return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $reason]);
        }

        $facilityRequest->status = 'Declined';
        $facilityRequest->decline_reason = $request->input('reason');
        $facilityRequest->declined_at = now();
        $facilityRequest->save();

        // Notify requester via email (declined by Division Chief)
        try {
            $requesterEmail = $facilityRequest->requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = \App\Models\User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($facilityRequest->requester?->division?->division_chief_id) {
                $u = \App\Models\User::find($facilityRequest->requester?->division?->division_chief_id);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Declined', $facilityRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request declined notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $facilityRequest->decline_reason]);
    }

    /**
     * Approve facility request by GSU Head via signed link
     */
    public function approveByGSU(Request $request, FacilityRequest $facilityRequest, $gsu)
    {
        if ($facilityRequest->status === 'Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $facilityRequest->status = 'Approved';
        $facilityRequest->save();

        try {
            $requesterEmail = $facilityRequest->requester?->email ?? null;
            $approverName   = $gsu ? (\App\Models\User::find($gsu)?->name ?? 'GSU Head') : 'GSU Head';

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Approved', null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request GSU approved notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => false]);
    }

    public function showGsuDeclineForm(Request $request, FacilityRequest $facilityRequest, $gsu)
    {
        if ($facilityRequest->status === 'Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $postAction = route('facility-requests.gsu.decline.submit', ['facilityRequest' => $facilityRequest->id, 'gsu' => $gsu])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $facilityRequest, 'postAction' => $postAction]);
    }

    public function submitGsuDecline(Request $request, FacilityRequest $facilityRequest, $gsu)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($facilityRequest->status, ['Approved', 'Declined'])) {
            $reason = $facilityRequest->decline_reason ?? '—';
            return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $reason]);
        }

        $facilityRequest->status = 'Declined';
        $facilityRequest->decline_reason = $request->input('reason');
        $facilityRequest->declined_at = now();
        $facilityRequest->save();

        // Notify requester
        try {
            $requesterEmail = $facilityRequest->requester?->email ?? null;
            $approverName = null;
            if ($gsu) {
                $u = \App\Models\User::find($gsu);
                $approverName = $u?->name ?? 'GSU Head';
            } else {
                $approverName = 'GSU Head';
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Declined', $facilityRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request GSU declined notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $facilityRequest->decline_reason]);
    }

    /**
     * Approve facility request by FAD Chief via signed link — final approval
     */
    public function approveByFAD(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        if ($facilityRequest->status === 'Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $facilityRequest->status = 'Approved';
        $facilityRequest->save();

        // Notify requester — fully approved
        try {
            $requesterEmail = $facilityRequest->requester?->email ?? null;
            $approverName = $chief ? (\App\Models\User::find($chief)?->name ?? 'FAD Chief') : 'FAD Chief';

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Approved', null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request FAD approved notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => false]);
    }

    public function showFadDeclineForm(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        if ($facilityRequest->status === 'Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $postAction = route('facility-requests.fad.decline.submit', ['facilityRequest' => $facilityRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $facilityRequest, 'postAction' => $postAction]);
    }

    public function submitFadDecline(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($facilityRequest->status, ['Approved', 'Declined'])) {
            $reason = $facilityRequest->decline_reason ?? '—';
            return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $reason]);
        }

        $facilityRequest->status = 'Declined';
        $facilityRequest->decline_reason = $request->input('reason');
        $facilityRequest->declined_at = now();
        $facilityRequest->save();

        try {
            $requesterEmail = $facilityRequest->requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = \App\Models\User::find($chief);
                $approverName = $u?->name ?? 'FAD Chief';
            } else {
                $approverName = 'FAD Chief';
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Declined', $facilityRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request FAD declined notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $facilityRequest->decline_reason]);
    }

    public function destroy(FacilityRequest $facilityRequest)
    {
        $isAdmin = auth()->user()->isSuperAdmin();
        if (! $isAdmin) abort(403);
        $facilityRequest->delete();
        return redirect()->route('facility-requests.index');
    }

    /**
     * Return JSON bookings for calendar display.
     * Includes Approved requests and expands multi-day ranges.
     */
    public function bookings(Request $request)
    {
        $rows = FacilityRequest::where('status', 'Approved')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (! $r->date_start) continue;
            $start = Carbon::parse($r->date_start);
            $end = $r->date_end ? Carbon::parse($r->date_end) : Carbon::parse($r->date_start);

            // normalize venues to array
            $venues = $r->venue ?? [];
            if (! is_array($venues)) {
                $venues = $venues ? [$venues] : [];
            }

            for ($dt = $start->copy(); $dt->lte($end); $dt->addDay()) {
                $dateStr = $dt->toDateString();
                if (empty($venues)) {
                    $out[] = [
                        'id' => $r->id,
                        'facility_id' => null,
                        'facility_name' => null,
                        'date' => $dateStr,
                        'start_time' => $r->time_start,
                        'end_time' => $r->time_end,
                        'activity' => $r->activity,
                        'status' => $r->status,
                    ];
                } else {
                    foreach ($venues as $vid) {
                        $fname = optional(Facility::find($vid))->name ?? null;
                        $out[] = [
                            'id' => $r->id,
                            'facility_id' => $vid,
                            'facility_name' => $fname,
                            'date' => $dateStr,
                            'start_time' => $r->time_start,
                            'end_time' => $r->time_end,
                            'activity' => $r->activity,
                            'status' => $r->status,
                        ];
                    }
                }
            }
        }

        return response()->json($out);
    }

    /**
     * Return facility requests for a specific date (any status).
     * Includes whether an associated IT Job Request exists and its status.
     */
    public function byDate(Request $request)
    {
        $request->validate(['date' => 'required|date']);
        $date = $request->input('date');

        $rows = FacilityRequest::with('requester.division')
            ->where(function ($q) use ($date) {
                $q->where('date_start', '<=', $date)
                  ->where(function ($q2) use ($date) {
                      $q2->where('date_end', '>=', $date)
                         ->orWhereNull('date_end');
                  });
            })
            ->get();

        $out = $rows->map(function ($r) {
            $venues = $r->venue ?? [];
            if (! is_array($venues)) {
                $venues = $venues ? [$venues] : [];
            }

            $facilityNames = [];
            if (! empty($venues)) {
                $facilityNames = \App\Models\Facility::whereIn('id', $venues)->pluck('name')->toArray();
            }

            $itjr = \App\Models\ITJobRequest::where('facility_request_id', $r->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

            return [
                'id' => $r->id,
                'activity' => $r->activity,
                'venue' => $facilityNames,
                'requester_name' => $r->requester?->name ?? null,
                'requester_unit' => $r->requester?->division?->division_name ?? $r->unit ?? null,
                'date_start' => $r->date_start,
                'date_end' => $r->date_end,
                'time_start' => $r->time_start,
                'time_end' => $r->time_end,
                'status' => $r->status,
                'has_it_job' => (bool) $itjr,
                'it_job_status' => $itjr?->status ?? null,
                'it_job_id' => $itjr?->id ?? null,
            ];
        })->values();

        return response()->json($out);
    }

    /**
     * Show a printable view for a facility request.
     * Only accessible to Admin and GSU Head via route middleware.
     */
    public function printTicket(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $request->user();

        if (! $user->hasPermission('facilities.manage')) {
            abort(403);
        }

        $facilityRequest->load(['requester']);
        $sigs = $this->loadSigsForPrint(FacilityRequest::class, $facilityRequest->id);

        return view('facility_requests.print_ticket', [
            'request' => $facilityRequest,
            'sigs'    => $sigs,
        ]);
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

        // Get division IDs where this user is chief
        $divisionIds = Division::where('division_chief_id', $user->id)->pluck('id');

        $requests = FacilityRequest::with('requester:id,name,division_id')
            ->where('status', 'Pending')
            ->whereHas('requester', fn ($q) => $q->whereIn('division_id', $divisionIds))
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('activity', 'like', "%{$search}%")
                      ->orWhere('purpose',  'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('FacilityRequests/DivisionChiefApproval', [
            'requests'     => $requests,
            'filters'      => ['search' => $search],
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
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

        $requests = FacilityRequest::with('requester:id,name')
            ->where('status', 'Pending FAD Approval')
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('activity', 'like', "%{$search}%")
                      ->orWhere('purpose',  'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('FacilityRequests/FADApproval', [
            'requests'     => $requests,
            'filters'      => ['search' => $search],
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    public function fadAction(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $request->user();
        $isFAD = str_contains($user->position ?? '', 'FAD') || $user->hasRole('Administrator');
        if (! $isFAD) abort(403);

        $request->validate(['action' => 'required|in:approve,reject']);

        if ($request->action === 'approve') {
            $facilityRequest->update(['status' => 'Approved']);
            if ($facilityRequest->requester) { NotificationService::notifyUser($facilityRequest->requester, 'Facility Request', "#{$facilityRequest->id}", 'Approved by FAD', route('facility-requests.index')); }

            $this->performSign($request, FacilityRequest::class, $facilityRequest->id,
                'fad_approval',
                "Facility Request #{$facilityRequest->id}",
                FacilityRequest::class . $facilityRequest->id . 'fad_approval'
            );

            // Notify requester — FAD is the final approver
            try {
                $requesterEmail = $facilityRequest->requester?->email ?? null;
                if ($requesterEmail) {
                    \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Approved', null, $user->name));
                }
            } catch (\Throwable $e) {
                logger()->error('Facility FAD approved email failed', ['error' => $e->getMessage()]);
            }
        } else {
            $request->validate(['reason' => 'nullable|string|max:1000']);
            $facilityRequest->update(['status' => 'Declined', 'decline_reason' => $request->input('reason') ?? 'Declined by FAD Chief.', 'declined_at' => now()]);
            if ($facilityRequest->requester) { NotificationService::notifyUser($facilityRequest->requester, 'Facility Request', "#{$facilityRequest->id}", 'Declined by FAD', route('facility-requests.index')); }
            try {
                $requesterEmail = $facilityRequest->requester?->email ?? null;
                if ($requesterEmail) {
                    \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Declined', $facilityRequest->decline_reason, $user->name));
                }
            } catch (\Throwable $e) {
                logger()->error('Facility FAD declined email failed', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'FAD action recorded.');
    }

    public function ocdApproval(Request $request)
    {
        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = FacilityRequest::with('requester:id,name')
            ->where('status', 'Approved')
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('activity', 'like', "%{$search}%")
                      ->orWhere('purpose',  'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('FacilityRequests/OCDApproval', [
            'requests'     => $requests,
            'filters'      => ['search' => $search],
            'hasPin'       => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function ocdAction(Request $request, FacilityRequest $facilityRequest)
    {
        $request->validate(['action' => 'required|in:approve,reject']);

        if ($request->action === 'approve') {
            $facilityRequest->update(['status' => 'OCD Approved']);

            $this->performSign($request, FacilityRequest::class, $facilityRequest->id,
                'ocd_approval',
                "Facility Request #{$facilityRequest->id}",
                FacilityRequest::class . $facilityRequest->id . 'ocd_approval'
            );
        } else {
            $request->validate(['reason' => 'nullable|string|max:1000']);
            $facilityRequest->update([
                'status'         => 'Declined',
                'decline_reason' => $request->input('reason') ?? 'Declined by OCD.',
                'declined_at'    => now(),
            ]);
        }

        return back()->with('success', 'OCD action recorded.');
    }

    public function signCompletion(Request $request, FacilityRequest $facilityRequest)
    {
        if ($facilityRequest->requestor_id !== $request->user()->id) {
            abort(403);
        }

        $this->performSign(
            $request,
            FacilityRequest::class,
            $facilityRequest->id,
            'completion',
            "Facility Request #{$facilityRequest->id}",
            FacilityRequest::class . $facilityRequest->id . 'completion'
        );

        return response()->json(['ok' => true]);
    }
}
