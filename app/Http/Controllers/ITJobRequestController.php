<?php

namespace App\Http\Controllers;

use App\Models\ITJobRequest;
use App\Models\ITJRTrackingLog;
use App\Models\ITJobCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ITJobRequestController extends Controller
{
    /**
     * Show all requests
     * - Admin sees everything
     * - Regular user sees only their own
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $requests = ITJobRequest::query()
            ->when(
                $user->role->name !== 'Administrator', // ✅ fixed role check
                fn ($q) => $q->where('user_id', $user->id)
            )
            ->with([
                'user:id,name',
                'trackingLogs:id,it_job_request_id,status,remarks,created_at'
            ])
            ->latest()
            ->get();
        
        $categories = ITJobCategory::orderBy('name')->get();
        
        // ✅ Get Division Chiefs & Administrators
        $divisionChiefs = \App\Models\User::whereHas('role', fn($q) => 
            $q->where('name', 'DivisionChief')
        )->select('id', 'name')->get();

        $administrators = \App\Models\User::whereHas('role', fn($q) => 
            $q->where('name', 'Administrator')
        )->select('id', 'name')->get();

        return Inertia::render('ITJobRequests/Index', [
            'requests' => $requests,
            'categories' => $categories,
            'divisionChiefs' => $divisionChiefs,
            'administrators' => $administrators,
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        return Inertia::render('ITJobRequests/Create');
    }

    /**
     * Store a new request
     */
    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'divisionchief' => 'required|string',
            'assignedto' => 'required|string',
        ]);

        // Generate ITJR number: yyyy-mm-#### (sequence)
        $prefix = now()->format('Y-m');
        $latestSeq = ITJobRequest::where('itjr_no', 'like', $prefix . '-%')
            ->select(DB::raw("MAX(CAST(SUBSTRING_INDEX(itjr_no, '-', -1) AS UNSIGNED)) as seq"))
            ->value('seq');

        $nextSeq = $latestSeq ? $latestSeq + 1 : 1;
        $itjrNo = sprintf("%s-%04d", $prefix, $nextSeq);

        $jobRequest = ITJobRequest::create([
            'user_id'     => $request->user()->id,
            'category'    => $request->category,
            'title'       => $request->title,
            'description' => $request->description,
            'itjr_no'     => $itjrNo,
            'status'      => 'Pending Division Chief Approval',
            'divisionchief' => $request->divisionchief,
            'assignedto'  => $request->assignedto,
            'feedback'    => 0,
        ]);

        // Tracking log
        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'Submitted IT Job Request',
            'remarks' => 'Request submitted by user.',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Request submitted!');
    }

    /**
     * List requests pending Division Chief approval
     */
    public function forApproval(Request $request)
    {
        $user = $request->user();

        if (! $user->role || $user->role->name !== 'DivisionChief') {
            abort(403, 'Unauthorized');
        }

        $requests = ITJobRequest::with('user')
            ->where('divisionchief', $user->name) // 👈 only requests assigned to this Division Chief
            ->where('status', 'Pending Division Chief Approval')
            ->get();

        return Inertia::render('ITJobRequests/ForApprovalITJR', [
            'requests' => $requests
        ]);
    }

    public function approveByDivisionChief(Request $request, ITJobRequest $jobRequest)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        if ($request->action === 'approve') {
            $jobRequest->update([
                'division_chief_approval' => true,
                'dc_approval_date' => now(),
                'status' => 'Pending OCD Approval',
            ]);

            ITJRTrackingLog::create([
                'it_job_request_id' => $jobRequest->id,
                'status' => 'Division Chief Approved',
                'remarks' => 'Approved by Division Chief.',
                'updated_by' => $request->user()->id,
            ]);
        } else {
            $jobRequest->update([
                'status' => 'Rejected by Division Chief',
            ]);

            ITJRTrackingLog::create([
                'it_job_request_id' => $jobRequest->id,
                'status' => 'Division Chief Rejected',
                'remarks' => 'Rejected by Division Chief.',
                'updated_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Division Chief action recorded!');
    }

    public function show($id)
    {
        $jobRequest = ITJobRequest::with('user')->findOrFail($id);

        return Inertia::render('ITJobRequests/Show', [
            'request' => $jobRequest,
        ]);
    }
    public function ocdApproval(Request $request)
    {
        $user = $request->user();

        if (! $user->role || $user->role->name !== 'OCD') {
            abort(403, 'Unauthorized');
        }

        $requests = ITJobRequest::with('user')
            ->where('status', 'Pending OCD Approval')
            ->get();

        return Inertia::render('ITJobRequests/OCDApprovalITJR', [
            'requests' => $requests
        ]);
    }
   
    public function approveByOCD(Request $request, ITJobRequest $jobRequest)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        if ($request->action === 'approve') {
            $jobRequest->update([
                'ocd_approval' => true,
                'ocd_approval_date' => now(),
                'status' => 'In Progress',
            ]);

            ITJRTrackingLog::create([
                'it_job_request_id' => $jobRequest->id,
                'status' => 'OCD Approved',
                'remarks' => 'Request Approved by OCD.',
                'updated_by' => $request->user()->id,
            ]);
        } else {
            $jobRequest->update([
                'status' => 'Rejected by OCD',
            ]);

            ITJRTrackingLog::create([
                'it_job_request_id' => $jobRequest->id,
                'status' => 'OCD Rejected',
                'remarks' => 'Rejected by OCD.',
                'updated_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'OCD action recorded!');
    }
    

    /**
     * Admin assessment
     */

    public function update(Request $request, $id)
    {
        $itJobRequest = ITJobRequest::findOrFail($id);

        $validated = $request->validate([
            'mis_assessment' => 'nullable|string',
            'expected_completion_date' => 'nullable|date',
            'action_taken' => 'nullable|string',
            'completed_at' => 'nullable|date',
        ]);

        // Determine status based on action_taken and completed_at
        $status = (empty($validated['action_taken']) && empty($validated['completed_at']))
            ? 'MIS Assessed the Request'
            : 'Acted by MIS';

        // Update MIS fields + set status + attended_by
        $itJobRequest->update(array_merge($validated, [
            'status' => $status,
            'attendedby' => $request->user()->name, // store the administrator's name
        ]));

        // Save tracking log
        ITJRTrackingLog::create([
            'it_job_request_id' => $itJobRequest->id,
            'status' => $status,
            'remarks' => "Assessment: {$request->mis_assessment}\nExpected Completion: {$request->expected_completion_date}\nAction Taken: {$request->action_taken}\nCompleted At: {$request->completed_at}",
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'MIS assessment updated successfully.');
    }





    public function assess(Request $request, ITJobRequest $jobRequest)
    {
        $request->validate([
            'admin_assessment' => 'required|string',
            'days_to_complete' => 'required|integer|min:1',
        ]);

        $jobRequest->update([
            'admin_assessment' => $request->admin_assessment,
            'status' => 'In Progress',
            'expected_completion_date' => now()->addDays($request->days_to_complete),
        ]);

        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'In Progress',
            'remarks' => 'Assessment done, work started.',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Assessment updated!');
    }

    /**
     * Mark as completed
     */
    public function complete(Request $request, ITJobRequest $jobRequest)
    {
        $jobRequest->update([
            'status' => 'Completed',
            'completed_at' => Carbon::now(),
        ]);

        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => 'Completed',
            'remarks' => 'Request completed successfully.',
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Request marked as completed!');
    }
}
