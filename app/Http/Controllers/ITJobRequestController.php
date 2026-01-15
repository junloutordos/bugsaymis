<?php

namespace App\Http\Controllers;

use App\Models\ITJobRequest;
use App\Models\ITJRTrackingLog;
use App\Models\ITJobCategory;
use App\Models\User;
use App\Models\ICTEquipment;
use App\Models\ICTPMSHistory;
use App\Mail\DivisionChiefITJRApprovalMail;
use App\Mail\OCDITJRApprovalMail;
use App\Mail\ITJRStatusMail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ITJobRequestController extends Controller
{
    /* =====================================================
     | INDEX
     |=====================================================*/
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = $this->getUserRole($user) === 'Administrator';

        $requests = ITJobRequest::with([
            'user:id,name',
            'divisionChief:id,name',
            'trackingLogs:id,it_job_request_id,status,remarks,created_at'
        ])
        ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
        ->latest()
        ->get();

        return Inertia::render('ITJobRequests/Index', [
            'requests' => $requests,
            'categories' => ITJobCategory::orderBy('name')->get(),
            'divisionChiefs' => User::whereHas('role', fn($q) => $q->where('name', 'DivisionChief'))->select('id','name')->get(),
            'administrators' => User::whereHas('role', fn($q) => $q->where('name', 'Administrator'))->select('id','name')->get(),

            // ✅ NEW: ICT Equipment list
            'ictEquipment' => ICTEquipment::orderBy('location')
                ->orderBy('description')
                ->get(),

            'isAdmin' => $isAdmin,
        ]);
    }

    private function getUserRole($user): string
    {
        return $user->role->name ?? '';
    }

    /* =====================================================
     | STORE
     |=====================================================*/
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'divisionchief_id' => 'required|exists:users,id',
            'assignedto' => 'required|exists:users,id',
        ]);

        // Generate ITJR number
        $prefix = now()->format('Y-m');
        $latestSeq = ITJobRequest::where('itjr_no', 'like', "{$prefix}-%")
            ->select(DB::raw("MAX(CAST(SUBSTRING_INDEX(itjr_no, '-', -1) AS UNSIGNED)) as seq"))
            ->value('seq');

        $validated['itjr_no'] = sprintf('%s-%04d', $prefix, ($latestSeq ?? 0) + 1);
        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'Pending Division Chief Approval';

        $jobRequest = ITJobRequest::create($validated);

        // Log creation
        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'Submitted IT Job Request',
            'remarks' => 'Request submitted by user.',
            'updated_by' => $request->user()->id,
        ]);

        // Send email to Division Chief with signed approve/decline links
        if ($jobRequest->divisionchief_id) {
            $chief = User::find($jobRequest->divisionchief_id);
            if ($chief && $chief->email) {
                $approveUrl = URL::signedRoute('it-job-requests.dc.approve', ['jobRequest' => $jobRequest->id, 'chief' => $chief->id], now()->addDays(7));
                $declineUrl = URL::signedRoute('it-job-requests.dc.decline', ['jobRequest' => $jobRequest->id, 'chief' => $chief->id], now()->addDays(7));

                try {
                    Mail::to($chief->email)->send(new DivisionChiefITJRApprovalMail($jobRequest, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    logger()->error('Failed to send Division Chief ITJR email', ['error' => $e->getMessage()]);
                }
            }
        }

        // Send email to Assigned Administrator
        if ($jobRequest->assignedto) {
            $admin = User::find($jobRequest->assignedto);
            if ($admin && $admin->email) {
                try {
                    Mail::to($admin->email)->send(new ITJRStatusMail($jobRequest, 'New Request Assigned', 'You have been assigned to this request.', 'Administrator'));
                } catch (\Throwable $e) {
                    logger()->error('Failed to send Administrator ITJR email', ['error' => $e->getMessage()]);
                }
            }
        }

        return back()->with('success', 'Request submitted successfully.');
    }

    /* =====================================================
     | DIVISION CHIEF APPROVE / DECLINE
     |=====================================================*/
    public function approveByDivisionChiefSigned(Request $request, ITJobRequest $jobRequest, $chief)
    {
        if ((int)$jobRequest->divisionchief_id !== (int)$chief) abort(403);
        if ($jobRequest->status !== 'Pending Division Chief Approval') return view('emails.itjr.already_approved', compact('jobRequest'));

        $jobRequest->update([
            'dc_approval_date' => now(),
            'status' => 'Pending OCD Approval',
        ]);

        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'Division Chief Approved',
            'remarks' => 'Approved via email link',
            'updated_by' => $chief,
        ]);

        // Notify requester
        if ($jobRequest->user && $jobRequest->user->email) {
            Mail::to($jobRequest->user->email)
                ->send(new ITJRStatusMail($jobRequest, 'Division Chief Approved', null, 'Division Chief'));
        }

        // Notify OCD users
        $ocdUsers = User::whereHas('role', fn($q)=>$q->where('name','OCD'))->get();
        foreach ($ocdUsers as $ocd) {
            if ($ocd->email) {
                $approveUrl = URL::signedRoute('it-job-requests.ocd.approve', ['jobRequest'=>$jobRequest->id,'ocd'=>$ocd->id], now()->addDays(7));
                $declineUrl = URL::signedRoute('it-job-requests.ocd.decline', ['jobRequest'=>$jobRequest->id,'ocd'=>$ocd->id], now()->addDays(7));
                Mail::to($ocd->email)->send(new OCDITJRApprovalMail($jobRequest, $approveUrl, $declineUrl));
            }
        }
        // Notify Assigned Administrator
        if ($jobRequest->assignedto) {
            $admin = User::find($jobRequest->assignedto);
            if ($admin && $admin->email) {
                Mail::to($admin->email)
                    ->send(new ITJRStatusMail($jobRequest, 'Division Chief Approved', 'The request you are assigned to has been approved by Division Chief.', 'Administrator'));
            }
        }
        return view('emails.itjr.approved', compact('jobRequest'));
    }

    // Show the decline form (GET)
    public function showDivisionChiefDeclineForm(ITJobRequest $jobRequest, $chief)
    {
        // Ensure only the correct Division Chief can view
        if ((int)$jobRequest->divisionchief_id !== (int)$chief) {
            abort(403);
        }

        // Generate the POST action URL (without signed middleware)
        $postAction = route('it-job-requests.dc.decline.submit', [$jobRequest, $chief]);

        return view('emails.itjr.decline_form', [
            'jobRequest' => $jobRequest,
            'chief' => $chief,
            'postAction' => $postAction
        ]);
    }

    // Submit the decline (POST)
    public function submitDivisionChiefDecline(Request $request, ITJobRequest $jobRequest, $chief)
    {
        if ((int)$jobRequest->divisionchief_id !== (int)$chief) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        // Update job request
        $jobRequest->update([
            'status' => 'Rejected by Division Chief',
            'decline_reason' => $validated['reason'],
            'declined_at' => now(),
        ]);

        // Log the action
        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'Division Chief Rejected',
            'remarks' => $validated['reason'],
            'updated_by' => $chief,
        ]);

        // Notify the requester
        if ($jobRequest->user && $jobRequest->user->email) {
            Mail::to($jobRequest->user->email)
                ->send(new ITJRStatusMail(
                    $jobRequest,
                    'Rejected by Division Chief',
                    $validated['reason'],
                    'Division Chief'
                ));
        }

        return view('emails.itjr.declined', [
            'jobRequest' => $jobRequest,
            'reason' => $validated['reason'],
        ]);
    }
    /* =====================================================
     | OCD APPROVE / DECLINE
     |=====================================================*/
    public function approveByOCDSigned(Request $request, ITJobRequest $jobRequest, $ocd)
{
    // 1️⃣ Validate OCD user exists and has the OCD role
    $ocdUser = User::find($ocd);
    if (!$ocdUser || ($ocdUser->role->name ?? '') !== 'OCD') {
        abort(403, 'Unauthorized OCD user.');
    }

    // 2️⃣ Check if the job request is waiting for OCD approval
    if ($jobRequest->status !== 'Pending OCD Approval') {
        return view('emails.itjr.already_approved', compact('jobRequest'));
    }

    // 3️⃣ Update the request status and log the approval date
    $jobRequest->update([
        'ocd_approval_date' => now(),
        'status' => 'In Progress',
    ]);

    // 4️⃣ Create a tracking log
    ITJRTrackingLog::create([
        'it_job_request_id' => $jobRequest->id,
        'status' => 'OCD Approved',
        'remarks' => 'Approved via email link',
        'updated_by' => $ocdUser->id,
    ]);

    // 5️⃣ Notify the requester
    if ($jobRequest->user && $jobRequest->user->email) {
        try {
            Mail::to($jobRequest->user->email)
                ->send(new ITJRStatusMail($jobRequest, 'OCD Approved', null, 'OCD'));
        } catch (\Throwable $e) {
            logger()->error('Failed to send OCD approval email', [
                'job_request_id' => $jobRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    if ($jobRequest->assignedto) {
        $admin = User::find($jobRequest->assignedto);
        if ($admin && $admin->email) {
            Mail::to($admin->email)
                ->send(new ITJRStatusMail($jobRequest, 'OCD Approved', 'The request you are assigned to has been approved by OCD.', 'Administrator'));
        }
    }

    // 6️⃣ Return a confirmation view
    return view('emails.itjr.approved', compact('jobRequest'));
}


    // Show the OCD decline form (GET)
public function showOCDDeclineForm(ITJobRequest $jobRequest, $ocd)
    {
        if ((int) auth()->id() !== (int) $ocd) {
            abort(403, 'Unauthorized action.');
        }

        $postAction = route('it-job-requests.ocd.decline.submit', [
            'jobRequest' => $jobRequest->id,
            'ocd' => $ocd
        ]);

        return view('emails.itjr.ocd_decline_form', [
            'jobRequest' => $jobRequest,
            'ocd' => $ocd,
            'postAction' => $postAction
        ]);
    }

    // Submit OCD decline (POST)
    public function submitOCDDecline(Request $request, ITJobRequest $jobRequest, $ocd)
    {
        if ((int) auth()->id() !== (int) $ocd) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $jobRequest->update([
            'status' => 'Rejected by OCD',
            'decline_reason' => $validated['reason'],
            'declined_at' => now(),
        ]);

        // Log the action
        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'OCD Rejected',
            'remarks' => $validated['reason'],
            'updated_by' => $ocd,
        ]);

        // Notify requester
        if ($jobRequest->user && $jobRequest->user->email) {
            Mail::to($jobRequest->user->email)
                ->send(new ITJRStatusMail(
                    $jobRequest,
                    'Rejected by OCD',
                    $validated['reason'],
                    'OCD'
                ));
        }

        return view('emails.itjr.declined', [
            'jobRequest' => $jobRequest,
            'reason' => $validated['reason'],
        ]);
    }
    /* =====================================================
     | MIS ASSESSMENT & USER CONFIRMATION
     |=====================================================*/
    public function update(Request $request, $id)
{
    $jobRequest = ITJobRequest::findOrFail($id);

    $validated = $request->validate([
        'mis_assessment' => 'nullable|string',
        'expected_completion_date' => 'nullable|date',
        'action_taken' => 'nullable|string',
        'completed_at' => 'nullable|date',
        'ict_equipment_id' => 'nullable|exists:ict_equipments,id',
    ]);

    $isActedByMIS = !empty($validated['action_taken']) || !empty($validated['completed_at']);

    $status = $isActedByMIS
        ? 'Acted by MIS'
        : 'MIS Assessed the Request';

    // 🔄 Update IT Job Request
    $jobRequest->update(array_merge($validated, [
        'status' => $status,
        'attendedby' => $request->user()->name,
    ]));

    // 🧾 Tracking log
    ITJRTrackingLog::create([
        'it_job_request_id' => $jobRequest->id,
        'status' => $status,
        'remarks' => collect($validated)->filter()->implode("\n"),
        'updated_by' => $request->user()->id,
    ]);

    // 📘 Save to ICT PMS History (only if acted)
    if ($isActedByMIS && !empty($validated['ict_equipment_id'])) {
        ICTPMSHistory::create([
            'ict_pms_id'     => null,
            'equipment_id'   => $validated['ict_equipment_id'],
            'pms_date'       => now()->toDateString(),
            'description'    => 'IT Job Request Service (' . $jobRequest->itjr_no . ')',
            'type'           => 'Repair',
            'cost_of_repair' => 0.00,
            'remarks'        => $validated['action_taken']
                                ?? $validated['mis_assessment']
                                ?? 'Service action from IT Job Request',
            'created_by'     => $request->user()->id,
        ]);
    }

    // 📧 NOTIFY REQUESTER
    if ($jobRequest->user && $jobRequest->user->email) {
        try {
            Mail::to($jobRequest->user->email)->send(
                new ITJRStatusMail(
                    $jobRequest,
                    $isActedByMIS ? 'MIS Action Completed' : 'MIS Assessment Update',
                    $isActedByMIS
                        ? ($validated['action_taken'] ?? 'Your request has been acted upon by MIS.')
                        : ($validated['mis_assessment'] ?? 'Your request has been assessed by MIS.'),
                    'MIS'
                )
            );
        } catch (\Throwable $e) {
            logger()->error('Failed to send MIS update email', [
                'job_request_id' => $jobRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    return back()->with('success', 'MIS assessment saved.');
}


    public function confirmCompletion(Request $request, ITJobRequest $jobRequest)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:500',
        ]);

        $jobRequest->update([
            'status' => 'Request Completed',
            'rating' => $validated['rating'],
            'rating_remarks' => $validated['remarks'],
            'rated_at' => now(),
        ]);

        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'Request Completed',
            'remarks' => 'User confirmed completion and rated the service.',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Request confirmed and rated.');
    }
}
