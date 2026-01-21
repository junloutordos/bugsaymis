<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\WorkRequest;
use App\Models\Building;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\WorkRequestCreatedMail;
use App\Mail\WorkRequestStatusMail;
use App\Mail\WorkRequestForAssignmentMail;
use App\Mail\WorkRequestAssignedMail;
use App\Mail\WorkRequestFADApprovalMail;

class WorkRequestController extends Controller
{
    public function index()
    {
        $divisions = Building::select('id', 'name')->get();
        $offices = Room::select('id', 'name', 'building_id')->get();
        // only users with position containing 'Skilled' are available for assignment
        $users = User::select('id', 'name', 'position')
            ->where('position', 'like', '%Skilled%')
            ->get();

        // also expose under a clear key for the frontend
        $skilledUsers = $users;

        $workRequests = WorkRequest::with(['division', 'office', 'assignedUser', 'requester', 'actedBy'])->get();

        return Inertia::render('GeneralServices/WorkRequest', [
            'divisions' => $divisions,
            'offices' => $offices,
            'users' => $users,
            'skilledUsers' => $skilledUsers,
            'workRequests' => $workRequests,
        ]);
    }

    public function store(Request $request)
    {
        // Only accept fields intended at creation time. Assignment and completion
        // details are handled later by GSU Head, so they are not accepted here.
        $data = $request->validate([
            'issue' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:Low,Normal,High',
            'location_division_id' => 'nullable|exists:buildings,id',
            'location_office_id' => 'nullable|exists:rooms,id',
            'expected_completion_date' => 'nullable|date',
        ]);

        $data['requester_id'] = Auth::id();
        $data['status'] = 'Pending';

        $wr = WorkRequest::create($data);

        // try to locate division chief (if building selected)
        $chiefEmail = null;
        $chiefUser = null;
        if (! empty($data['location_division_id'])) {
            // attempt to find a user who is marked as division chief for this building's division mapping
            // fallback: look for a user whose office/room likely belongs to the building
            $building = Building::find($data['location_division_id']);
            if ($building) {
                // Find office ids for rooms in this building
                $officeIds = Room::where('building_id', $building->id)
                                 ->whereNotNull('office_id')
                                 ->pluck('office_id')
                                 ->unique()
                                 ->toArray();

                if (! empty($officeIds)) {
                    // find divisions for those offices
                    $divisionIds = \App\Models\Office::whereIn('id', $officeIds)
                                      ->whereNotNull('division_id')
                                      ->pluck('division_id')
                                      ->unique()
                                      ->toArray();

                    if (! empty($divisionIds)) {
                        // find a division that has a division_chief assigned
                        $division = \App\Models\Division::whereIn('id', $divisionIds)
                                    ->whereNotNull('division_chief_id')
                                    ->first();

                        if ($division && $division->division_chief_id) {
                            $chiefUser = User::find($division->division_chief_id);
                            if ($chiefUser && $chiefUser->email) {
                                $chiefEmail = $chiefUser->email;
                            }
                        }
                    }
                }
            }
        }

        if ($chiefEmail) {
            // persist division_chief_id on the request so signed links can validate
            if ($chiefUser) {
                $wr->division_chief_id = $chiefUser->id;
                $wr->save();
            }
            try {
                $approveUrl = $chiefUser ? URL::signedRoute('work-requests.approve', ['workRequest' => $wr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : route('work-requests.index');
                $declineUrl = $chiefUser ? URL::signedRoute('work-requests.decline', ['workRequest' => $wr->id, 'chief' => $chiefUser->id], now()->addDays(7)) : null;
                Mail::to($chiefEmail)->send(new WorkRequestCreatedMail($wr, $approveUrl, $declineUrl));
            } catch (\Throwable $e) {
                logger()->error('Failed to send work request email', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('work-requests.index')->with('success', 'Work request created.');
    }

    /**
     * Approve work request via signed link from Division Chief
     */
    public function approveByDivisionChief(Request $request, WorkRequest $workRequest, $chief)
    {
        if ($workRequest->division_chief_id && (int) $workRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }
        if ($workRequest->status === 'Approved' || $workRequest->status === 'Division Approved') {
            return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => true]);
        }
        // mark as approved by Division Chief but do NOT notify requester yet
        $workRequest->status = 'Division Approved';
        $workRequest->save();

        // Notify all GSU Head users to assign staff
        try {
            $gsuHeads = User::whereHas('role', function($q){ $q->where('name', 'like', '%GSU Head%'); })->get();
            foreach ($gsuHeads as $gsu) {
                if ($gsu->email) {
                    Mail::to($gsu->email)->send(new WorkRequestForAssignmentMail($workRequest, $chief));
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send GSU Head notification', ['error' => $e->getMessage()]);
        }

        return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => false]);
    }

    // FAD approval handlers (signed)
    public function approveByFADChief(Request $request, WorkRequest $workRequest, $chief)
    {
        if ($workRequest->status === 'FAD Approved') {
            return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => true]);
        }

        $workRequest->status = 'FAD Approved';
        $workRequest->save();

        // Notify requester about FAD approval
        try {
            $requesterEmail = $workRequest->requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = \App\Models\User::find($chief);
                $approverName = $u?->name ?? null;
            }
            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new WorkRequestStatusMail($workRequest, 'FAD Approved', null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send work request FAD approved notification', ['error' => $e->getMessage()]);
        }

        return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => false]);
    }

    public function showFADDeclineForm(Request $request, WorkRequest $workRequest, $chief)
    {
        if ($workRequest->status === 'FAD Approved') {
            return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => true]);
        }

        $postAction = route('work-requests.fad.decline.submit', ['workRequest' => $workRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $workRequest, 'postAction' => $postAction]);
    }

    public function submitFADDecline(Request $request, WorkRequest $workRequest, $chief)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $workRequest->status = 'Declined';
        $workRequest->decline_reason = $request->input('reason');
        $workRequest->declined_at = now();
        $workRequest->save();

        // Notify requester via email (declined by FAD Chief)
        try {
            $requesterEmail = $workRequest->requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = \App\Models\User::find($chief);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new WorkRequestStatusMail($workRequest, 'Declined', $workRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send work request declined notification', ['error' => $e->getMessage()]);
        }

        return view('work_request_declined', ['facilityRequest' => $workRequest, 'reason' => $workRequest->decline_reason]);
    }

    public function showDeclineForm(Request $request, WorkRequest $workRequest, $chief)
    {
        if ($workRequest->division_chief_id && (int) $workRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        if ($workRequest->status === 'Approved') {
            return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => true]);
        }

        $postAction = route('work-requests.decline.submit', ['workRequest' => $workRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $workRequest, 'postAction' => $postAction]);
    }

    public function submitDecline(Request $request, WorkRequest $workRequest, $chief)
    {
        if ($workRequest->division_chief_id && (int) $workRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($workRequest->status, ['Approved','Declined'])) {
            $reason = $workRequest->decline_reason ?? '—';
            return view('work_request_declined', ['facilityRequest' => $workRequest, 'reason' => $reason]);
        }

        $workRequest->status = 'Declined';
        $workRequest->decline_reason = $request->input('reason');
        $workRequest->declined_at = now();
        $workRequest->save();

        // Notify requester via email (declined by Division Chief)
        try {
            $requesterEmail = $workRequest->requester?->email ?? null;
            $approverName = null;
            if ($chief) {
                $u = \App\Models\User::find($chief);
                $approverName = $u?->name ?? null;
            } elseif ($workRequest->division_chief_id) {
                $u = \App\Models\User::find($workRequest->division_chief_id);
                $approverName = $u?->name ?? null;
            }

            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new WorkRequestStatusMail($workRequest, 'Declined', $workRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send work request declined notification', ['error' => $e->getMessage()]);
        }

        return view('work_request_declined', ['facilityRequest' => $workRequest, 'reason' => $workRequest->decline_reason]);
    }

    public function update(Request $request, WorkRequest $workRequest)
    {
        $data = $request->validate([
            'issue' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:Low,Normal,High',
            'location_division_id' => 'nullable|exists:buildings,id',
            'location_office_id' => 'nullable|exists:rooms,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'acted_by_id' => 'nullable|exists:users,id',
            'expected_completion_date' => 'nullable|date',
            'action_taken' => 'nullable|string',
            'date_completed' => 'nullable|date',
            'status' => 'nullable|string',
        ]);


        // detect assignment change
        $previousAssigned = $workRequest->assigned_user_id;

        $workRequest->update($data);

        // if newly assigned, notify assigned staff and send to FAD Chief for approval
        if (empty($previousAssigned) && ! empty($workRequest->assigned_user_id)) {
            try {
                // notify assigned staff
                $assigned = \App\Models\User::find($workRequest->assigned_user_id);
                if ($assigned && $assigned->email) {
                    Mail::to($assigned->email)->send(new WorkRequestAssignedMail($workRequest));
                }

                // set status to pending FAD approval
                $workRequest->status = 'Pending FAD Approval';
                $workRequest->save();

                // notify FAD Chiefs (match by position) with signed approve/decline links
                $fadChiefs = User::select('id','email','position')
                            ->where('position', 'like', '%FAD%')
                            ->get();
                foreach ($fadChiefs as $fad) {
                    if (! $fad->email) continue;
                    $approveUrl = URL::signedRoute('work-requests.fad.approve', ['workRequest' => $workRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('work-requests.fad.decline', ['workRequest' => $workRequest->id, 'chief' => $fad->id], now()->addDays(7));
                    Mail::to($fad->email)->send(new WorkRequestFADApprovalMail($workRequest, $approveUrl, $declineUrl));
                }
            } catch (\Throwable $e) {
                logger()->error('Failed during post-assignment notifications', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('work-requests.index')->with('success', 'Work request updated.');
    }

    public function destroy(WorkRequest $workRequest)
    {
        $workRequest->delete();
        return redirect()->route('work-requests.index')->with('success', 'Work request deleted.');
    }
}
