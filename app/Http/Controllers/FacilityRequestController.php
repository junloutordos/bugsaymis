<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\FacilityRequest;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\FacilityRequestCreatedMail;
use App\Models\Division;
use App\Models\User;
use App\Models\Facility;

class FacilityRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';
        $canViewAll = in_array($role, ['Administrator', 'GSU Head']);

        $requests = FacilityRequest::when(!$canViewAll, fn($q) => $q->where('email', $user->email))
            ->latest()
            ->get();

        // also provide facilities list for venue selection
        $facilities = \App\Models\Facility::orderBy('name')->get(['id','name']);

        // map venue IDs to facility names for display
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

        return Inertia::render('FacilityRequests/Index', [
            'requests' => $requests,
            'facilities' => $facilities,
        ]);
    }

    public function store(Request $request)
    {
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
            'equipment' => 'nullable|array',
            'equipment.*' => 'in:Chairs,Tables,Microphone,Whiteboard,Projector,Electric Fans,Airconditioner,Trashbins',
            'equipment_quantities' => 'nullable|array',
            'equipment_quantities.*' => 'nullable|integer|min:1',
            'time_start' => 'nullable|date_format:H:i',
            'time_end' => 'nullable|date_format:H:i',
        ]);

        $data = $request->only([
            'unit','activity','purpose','nature',
            'date_start','date_end','time_start','time_end',
            'male','female','venue','chairs','tables','mic','whiteboard','projector','elecfans','aircon','trashbins','equipment','equipment_quantities',
            'others','remarks','email','participants','reference_no'
        ]);

        // set requestor and email from authenticated user
        $user = $request->user();
        if ($user) {
            $data['requestor'] = $user->name;
            $data['email'] = $user->email;
            // set unit from user's division name if available
            if (method_exists($user, 'division') && $user->division) {
                $data['unit'] = $user->division->division_name ?? $user->office ?? null;
            } else {
                $data['unit'] = $user->office ?? null;
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
            // persist division_chief_id on the request so signed links can validate
            if ($chiefUser) {
                $fr->division_chief_id = $chiefUser->id;
                $fr->save();
            }
            try {
                // create signed approve/decline links (valid 7 days)
                $approveUrl = $chiefUser ? URL::signedRoute('facility-requests.approve', ['facilityRequest' => $fr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : route('facility-requests.index');
                $declineUrl = $chiefUser ? URL::signedRoute('facility-requests.decline', ['facilityRequest' => $fr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : null;
                Mail::to($chiefEmail)->send(new FacilityRequestCreatedMail($fr, $approveUrl, $declineUrl));
            } catch (\Throwable $e) {
                logger()->error('Failed to send facility request email', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('facility-requests.index')->with('success', 'Facility request submitted');
    }

    public function update(Request $request, FacilityRequest $facilityRequest)
    {
        $isAdmin = ($request->user()->role->name ?? '') === 'Administrator';
        if (! $isAdmin) abort(403);

        $facilityRequest->update($request->all());
        return redirect()->route('facility-requests.index');
    }

    /**
     * Approve facility request via signed link from Division Chief
     */
    public function approveByDivisionChief(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        // Signed middleware ensures URL integrity. Verify the chief in the link matches the assigned approver when present.
        if ($facilityRequest->division_chief_id && (int) $facilityRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }
        if ($facilityRequest->status === 'Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $facilityRequest->status = 'Approved';
        $facilityRequest->save();

        // Notify requester via email (approved by Division Chief)
        try {
            $requesterEmail = $facilityRequest->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = \App\Models\User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($facilityRequest->division_chief_id) {
                $u = \App\Models\User::find($facilityRequest->division_chief_id);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Approved', null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request approved notification', ['error' => $e->getMessage()]);
        }

        // Notify GSU Head users with signed approve/decline links (replacing previous OCD recipients)
        try {
            $gsuUsers = \App\Models\User::whereHas('role', function($q) { $q->where('name', 'GSU Head'); })->get();
            foreach ($gsuUsers as $gsuUser) {
                if ($gsuUser->email) {
                    try {
                        $approveUrl = URL::signedRoute('facility-requests.gsu.approve', ['facilityRequest' => $facilityRequest->id, 'gsu' => $gsuUser->id], now()->addDays(7));
                        $declineUrl = URL::signedRoute('facility-requests.gsu.decline', ['facilityRequest' => $facilityRequest->id, 'gsu' => $gsuUser->id], now()->addDays(7));
                        \Mail::to($gsuUser->email)->send(new \App\Mail\FacilityRequestGSUMail($facilityRequest, $approveUrl, $declineUrl));
                    } catch (\Throwable $e) {
                        logger()->error('Failed to send facility request GSU notification', ['error' => $e->getMessage(), 'email' => $gsuUser->email]);
                    }
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to queue GSU notifications for facility request', ['error' => $e->getMessage()]);
        }

        return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => false]);
    }

    public function showDeclineForm(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        if ($facilityRequest->division_chief_id && (int) $facilityRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        if ($facilityRequest->status === 'Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $postAction = route('facility-requests.decline.submit', ['facilityRequest' => $facilityRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $facilityRequest, 'postAction' => $postAction]);
    }

    public function submitDecline(Request $request, FacilityRequest $facilityRequest, $chief)
    {
        if ($facilityRequest->division_chief_id && (int) $facilityRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($facilityRequest->status, ['Approved','Declined'])) {
            $reason = $facilityRequest->decline_reason ?? '—';
            return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $reason]);
        }

        $facilityRequest->status = 'Declined';
        $facilityRequest->decline_reason = $request->input('reason');
        $facilityRequest->declined_at = now();
        $facilityRequest->save();

        // Notify requester via email (declined by Division Chief)
        try {
            $requesterEmail = $facilityRequest->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = \App\Models\User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($facilityRequest->division_chief_id) {
                $u = \App\Models\User::find($facilityRequest->division_chief_id);
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
     * Approve facility request by OCD via signed link
     */
    public function approveByOCD(Request $request, FacilityRequest $facilityRequest, $ocd)
    {
        if ($facilityRequest->status === 'OCD Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $facilityRequest->status = 'OCD Approved';
        $facilityRequest->save();

        // Notify requester
        try {
            $requesterEmail = $facilityRequest->email ?? null;
            $approverName = null;
            if ($ocd) {
                $u = \App\Models\User::find($ocd);
                $approverName = $u?->name ?? 'Office of the Campus Director';
            } else {
                $approverName = 'Office of the Campus Director';
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'OCD Approved', null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request OCD approved notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => false]);
    }

    /**
     * Approve facility request by GSU Head via signed link
     */
    public function approveByGSU(Request $request, FacilityRequest $facilityRequest, $gsu)
    {
        if ($facilityRequest->status === 'OCD Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $facilityRequest->status = 'OCD Approved';
        $facilityRequest->save();

        // Notify requester
        try {
            $requesterEmail = $facilityRequest->email ?? null;
            $approverName = null;
            if ($gsu) {
                $u = \App\Models\User::find($gsu);
                $approverName = $u?->name ?? 'GSU Head';
            } else {
                $approverName = 'GSU Head';
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'OCD Approved', null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request GSU approved notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => false]);
    }

    public function showGsuDeclineForm(Request $request, FacilityRequest $facilityRequest, $gsu)
    {
        if ($facilityRequest->status === 'OCD Approved') {
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

        if (in_array($facilityRequest->status, ['Approved','Declined','OCD Approved','OCD Declined'])) {
            $reason = $facilityRequest->decline_reason ?? '—';
            return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $reason]);
        }

        $facilityRequest->status = 'OCD Declined';
        $facilityRequest->decline_reason = $request->input('reason');
        $facilityRequest->declined_at = now();
        $facilityRequest->save();

        // Notify requester
        try {
            $requesterEmail = $facilityRequest->email ?? null;
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

    public function showOcdDeclineForm(Request $request, FacilityRequest $facilityRequest, $ocd)
    {
        if ($facilityRequest->status === 'OCD Approved') {
            return view('facility_request_approved', ['facilityRequest' => $facilityRequest, 'already' => true]);
        }

        $postAction = route('facility-requests.ocd.decline.submit', ['facilityRequest' => $facilityRequest->id, 'ocd' => $ocd])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $facilityRequest, 'postAction' => $postAction]);
    }

    public function submitOcdDecline(Request $request, FacilityRequest $facilityRequest, $ocd)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($facilityRequest->status, ['Approved','Declined','OCD Approved','OCD Declined'])) {
            $reason = $facilityRequest->decline_reason ?? '—';
            return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $reason]);
        }

        $facilityRequest->status = 'OCD Declined';
        $facilityRequest->decline_reason = $request->input('reason');
        $facilityRequest->declined_at = now();
        $facilityRequest->save();

        // Notify requester
        try {
            $requesterEmail = $facilityRequest->email ?? null;
            $approverName = null;
            if ($ocd) {
                $u = \App\Models\User::find($ocd);
                $approverName = $u?->name ?? 'Office of the Campus Director';
            } else {
                $approverName = 'Office of the Campus Director';
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\FacilityRequestStatusMail($facilityRequest, 'Declined', $facilityRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send facility request OCD declined notification', ['error' => $e->getMessage()]);
        }

        return view('facility_request_declined', ['facilityRequest' => $facilityRequest, 'reason' => $facilityRequest->decline_reason]);
    }

    public function destroy(FacilityRequest $facilityRequest)
    {
        $isAdmin = (auth()->user()->role->name ?? '') === 'Administrator';
        if (! $isAdmin) abort(403);
        $facilityRequest->delete();
        return redirect()->route('facility-requests.index');
    }

    /**
     * Return JSON bookings for calendar display.
     * Includes both 'Approved' and 'OCD Approved' requests and expands multi-day ranges.
     */
    public function bookings(Request $request)
    {
        $rows = FacilityRequest::whereIn('status', ['Approved', 'OCD Approved'])
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
     * Show a printable view for a facility request.
     * Only accessible to Admin and GSU Head via route middleware.
     */
    public function printTicket(Request $request, FacilityRequest $facilityRequest)
    {
        $user = $request->user();
        $role = $user->role->name ?? '';

        if (! in_array($role, ['Administrator', 'GSU Head'])) {
            abort(403);
        }

        if ($facilityRequest->status !== 'OCD Approved') {
            abort(403, 'Request not ready for printing');
        }

        return view('facility_requests.print_ticket', ['request' => $facilityRequest]);
    }
}
