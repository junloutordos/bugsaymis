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
use App\Mail\WorkRequestCompletedMail;

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

        $user = Auth::user();
        $query = WorkRequest::with(['division', 'office', 'assignedUser', 'requester', 'actedBy'])->orderByDesc('created_at');

        $canViewAll = $user->hasAnyRole(['Administrator', 'GSU Head', 'DivisionChief'])
            || str_contains($user->position ?? '', 'FAD');

        if (! $canViewAll) {
            $query->where('requester_id', $user->id);
        }

        $workRequests = $query->get();

        $isDivisionChief = $user->hasRole('DivisionChief');

        return Inertia::render('GeneralServices/WorkRequest', [
            'divisions' => $divisions,
            'offices' => $offices,
            'users' => $users,
            'skilledUsers' => $skilledUsers,
            'workRequests' => $workRequests,
            'isDivisionChief' => $isDivisionChief,
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

        // Instead of sending initial notification to FAD/Division Chief,
        // send the first approval request to GSU Head(s).
        try {
            $gsuHeadRole = \App\Models\Role::where('name', 'GSU Head')->first();
            $gsuHeads = $gsuHeadRole
                ? User::whereRaw('FIND_IN_SET(?, role_id)', [$gsuHeadRole->id])->get()
                : collect();

            if ($gsuHeads->isEmpty()) {
                logger()->warning('No GSU Head users found to notify for new work request', ['work_request_id' => $wr->id]);
            }

            foreach ($gsuHeads as $gsu) {
                if (! $gsu->email) continue;
                $approveUrl = URL::signedRoute('work-requests.gsu.approve', ['workRequest' => $wr->id, 'gsu' => $gsu->id], now()->addDays(7));
                $declineUrl = URL::signedRoute('work-requests.gsu.decline', ['workRequest' => $wr->id, 'gsu' => $gsu->id], now()->addDays(7));
                Mail::to($gsu->email)->send(new \App\Mail\WorkRequestCreatedMail($wr, $approveUrl, $declineUrl, $gsu->id));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send GSU Head work request email', ['error' => $e->getMessage(), 'work_request_id' => $wr->id]);
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
            $gsuHeadRole2 = \App\Models\Role::where('name', 'GSU Head')->first();
            $gsuHeads = $gsuHeadRole2
                ? User::whereRaw('FIND_IN_SET(?, role_id)', [$gsuHeadRole2->id])->get()
                : collect();
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

    // Authenticated in-app approval by logged-in Division Chief
    public function approveInApp(Request $request, WorkRequest $workRequest)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('DivisionChief')) abort(403);

        if ($workRequest->division_chief_id && (int) $workRequest->division_chief_id !== (int) $user->id) abort(403);
        if (in_array($workRequest->status, ['Approved','Division Approved'])) return back()->with('success', 'Already processed');

        $workRequest->status = 'Division Approved';
        $workRequest->save();

        try {
            $gsuHeadRole3 = \App\Models\Role::where('name', 'GSU Head')->first();
            $gsuHeads = $gsuHeadRole3
                ? User::whereRaw('FIND_IN_SET(?, role_id)', [$gsuHeadRole3->id])->get()
                : collect();
            foreach ($gsuHeads as $gsu) {
                if ($gsu->email) {
                    Mail::to($gsu->email)->send(new WorkRequestForAssignmentMail($workRequest, $user->id));
                }
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send GSU Head notification', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Work request approved for assignment.');
    }

    public function declineInApp(Request $request, WorkRequest $workRequest)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('DivisionChief')) abort(403);
        if ($workRequest->division_chief_id && (int) $workRequest->division_chief_id !== (int) $user->id) abort(403);

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        if (in_array($workRequest->status, ['Approved','Declined'])) return back()->with('success', 'Already processed');

        $workRequest->status = 'Declined';
        $workRequest->decline_reason = $data['reason'] ?? null;
        $workRequest->declined_at = now();
        $workRequest->save();

        try {
            $requester = $workRequest->requester;
            if ($requester && $requester->email) {
                Mail::to($requester->email)->send(new WorkRequestStatusMail($workRequest, 'Declined'));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send work request declined notification', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Work request declined.');
    }

    /**
     * Approve work request via signed link from GSU Head (first-level approver)
     */
    public function approveByGSUHead(Request $request, WorkRequest $workRequest, $gsu)
    {
        // basic check: if already acted upon, show already page
        if (in_array($workRequest->status, ['GSU Approved','FAD Approved','Approved','Declined','Division Approved'])) {
            return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => true]);
        }

        // if an assigned_user_id was provided in the signed link, persist it
        $assigned = $request->query('assigned_user_id');
        if ($assigned) {
            $workRequest->assigned_user_id = $assigned;
        }

        // mark as GSU Approved
        $workRequest->status = 'GSU Approved';
        // record who acted (for audit/notifications)
        $workRequest->acted_by_id = $gsu;
        $workRequest->save();

        logger()->info('WorkRequest approved by GSU Head', ['work_request_id' => $workRequest->id, 'gsu_id' => $gsu]);

        // Notify FAD Chiefs for next-level approval
        try {
            $fadChiefs = User::select('id','email','position')
                        ->where('position', 'like', '%FAD%')
                        ->get();
            foreach ($fadChiefs as $fad) {
                if (! $fad->email) continue;
                $approveUrl = URL::signedRoute('work-requests.fad.approve', ['workRequest' => $workRequest->id, 'chief' => $fad->id], now()->addDays(7));
                $declineUrl = URL::signedRoute('work-requests.fad.decline', ['workRequest' => $workRequest->id, 'chief' => $fad->id], now()->addDays(7));
                Mail::to($fad->email)->send(new \App\Mail\WorkRequestFADApprovalMail($workRequest, $approveUrl, $declineUrl, $fad));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify FAD Chiefs after GSU approval', ['error' => $e->getMessage(), 'work_request_id' => $workRequest->id]);
        }

        return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => false]);
    }

    /**
     * Show decline form for GSU Head (signed link)
     */
    public function showGSUDeclineForm(Request $request, WorkRequest $workRequest, $gsu)
    {
        if (in_array($workRequest->status, ['GSU Approved','FAD Approved','Approved','Declined','Division Approved'])) {
            return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => true]);
        }

        $postAction = route('work-requests.gsu.decline.submit', ['workRequest' => $workRequest->id, 'gsu' => $gsu])
            . '?' . $request->getQueryString();

        return view('facility_request_decline', ['facilityRequest' => $workRequest, 'postAction' => $postAction]);
    }

    /**
     * Submit decline by GSU Head
     */
    public function submitGSUDecline(Request $request, WorkRequest $workRequest, $gsu)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($workRequest->status, ['GSU Approved','FAD Approved','Approved','Declined','Division Approved'])) {
            $reason = $workRequest->decline_reason ?? '—';
            return view('work_request_declined', ['facilityRequest' => $workRequest, 'reason' => $reason]);
        }

        $workRequest->status = 'Declined';
        $workRequest->decline_reason = $request->input('reason');
        $workRequest->declined_at = now();
        $workRequest->acted_by_id = $gsu;
        $workRequest->save();

        logger()->info('WorkRequest declined by GSU Head', ['work_request_id' => $workRequest->id, 'gsu_id' => $gsu, 'reason' => $workRequest->decline_reason]);

        // Notify requester that GSU Head declined
        try {
            $requesterEmail = $workRequest->requester?->email ?? null;
            $approverName = null;
            if ($gsu) {
                $u = \App\Models\User::find($gsu);
                $approverName = $u?->name ?? null;
            }
            if ($requesterEmail) {
                \Mail::to($requesterEmail)->send(new \App\Mail\WorkRequestStatusMail($workRequest, 'Declined', $workRequest->decline_reason ?? null, $approverName));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify requester after GSU decline', ['error' => $e->getMessage(), 'work_request_id' => $workRequest->id]);
        }

        return view('work_request_declined', ['facilityRequest' => $workRequest, 'reason' => $workRequest->decline_reason]);
    }

    // FAD approval handlers (signed)
    public function approveByFADChief(Request $request, WorkRequest $workRequest, $chief)
    {
        if ($workRequest->status === 'FAD Approved') {
            return view('work_request_approved', ['facilityRequest' => $workRequest, 'already' => true]);
        }

        $workRequest->status = 'FAD Approved';
        $workRequest->save();

        logger()->info('WorkRequest approved by FAD Chief', ['work_request_id' => $workRequest->id, 'fad_id' => $chief]);

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

            // Also notify the GSU Head who acted earlier (if any)
            if (! empty($workRequest->acted_by_id)) {
                $gsu = \App\Models\User::find($workRequest->acted_by_id);
                if ($gsu && $gsu->email) {
                    \Mail::to($gsu->email)->send(new WorkRequestStatusMail($workRequest, 'FAD Approved', null, $approverName));
                }
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

        logger()->info('WorkRequest declined by FAD Chief', ['work_request_id' => $workRequest->id, 'fad_id' => $chief, 'reason' => $workRequest->decline_reason]);

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

            // Also notify the GSU Head who acted earlier (if any)
            if (! empty($workRequest->acted_by_id)) {
                $gsu = \App\Models\User::find($workRequest->acted_by_id);
                if ($gsu && $gsu->email) {
                    \Mail::to($gsu->email)->send(new WorkRequestStatusMail($workRequest, 'Declined', $workRequest->decline_reason ?? null, $approverName));
                }
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
                    Mail::to($fad->email)->send(new WorkRequestFADApprovalMail($workRequest, $approveUrl, $declineUrl, $fad));
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

    /**
     * Mark a work request as completed with details provided by GSU Head or Administrator.
     */
    public function complete(Request $request, WorkRequest $workRequest)
    {
        // Authorization enforced by route middleware (role:Administrator|GSU Head)

        $data = $request->validate([
            'acted_by_id' => 'nullable|exists:users,id',
            'action_taken' => 'required|string|max:2000',
            'date_completed' => 'required|date',
        ]);

        $workRequest->acted_by_id = $data['acted_by_id'] ?? Auth::id();
        $workRequest->action_taken = $data['action_taken'];
        $workRequest->date_completed = $data['date_completed'];
        $workRequest->status = 'Completed';
        $workRequest->save();

        // Notify the requester
        try {
            $requesterEmail = $workRequest->requester?->email ?? null;
            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new WorkRequestCompletedMail($workRequest));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send work request completion email', ['error' => $e->getMessage()]);
        }

        return redirect()->route('work-requests.index')->with('success', 'Work request marked as completed.');
    }

    /**
     * Printable slip for a work request.
     */
    public function print(WorkRequest $workRequest)
    {
        $workRequest->load(['division', 'office', 'assignedUser', 'requester', 'actedBy']);
        return view('work_requests.print_ticket', ['workRequest' => $workRequest]);
    }
}
