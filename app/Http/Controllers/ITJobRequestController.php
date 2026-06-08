<?php

namespace App\Http\Controllers;

use App\Models\ITJobRequest;
use App\Models\ITJRTrackingLog;
use App\Models\ITJobCategory;
use App\Models\User;
use App\Services\NotificationService;
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
use App\Enums\ApprovalStep;
use App\Models\DigitalSignature;
use App\Services\DigitalSignatureService;
use App\Services\SnapshotService;
use App\Services\ITJobRequestPdfService;

class ITJobRequestController extends Controller
{
    public function __construct(
        private SnapshotService $snapshots,
        private ITJobRequestPdfService $pdfService,
        private DigitalSignatureService $sigService,
    ) {}
    /* =====================================================
     | INDEX
     |=====================================================*/
public function index(Request $request)
{
    $user    = $request->user();
    $isAdmin = $user->hasPermission('it.requests.manage');
    $search   = trim($request->query('search', ''));
    $category = trim($request->query('category', ''));
    $status   = trim($request->query('status', ''));
    $perPage  = min((int) $request->query('per_page', 15), 1000);

    $requests = ITJobRequest::select([
            'id', 'itjr_no', 'title', 'category', 'description', 'status',
            'user_id', 'divisionchief_id', 'assignedto', 'attendedby',
            'action_taken', 'created_at', 'updated_at',
            'mis_assessment', 'recommendation', 'expected_completion_date', 'completed_at',
            'ict_equipment_id', 'rating', 'rating_remarks', 'rated_at', 'pdf_path',
            'priority', 'queued_at',
        ])
        ->with([
            'user:id,name',
            'divisionChief:id,name',
            'assignedTo:id,name',
            'trackingLogs:id,it_job_request_id,status,remarks,created_at',
            'equipment' => fn($q) => $q->select('id', 'description', 'room_id', 'owner_id')
                ->with(['room:id,name,code', 'owner:id,name']),
        ])
        ->when(!$isAdmin, fn($q) => $q->where('user_id', $user->id))
        ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
            $inner->where('title', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhere('itjr_no', 'like', "%{$search}%")
                  ->orWhere('action_taken', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
        }))
        ->when($category, fn($q) => $q->where('category', $category))
        ->when($status,   fn($q) => $q->where('status',   $status))
        ->latest()
        ->paginate($perPage)
        ->withQueryString();

    return Inertia::render('ITJobRequests/Index', [
        'requests'       => $requests,
        'filters'        => ['search' => $search, 'category' => $category, 'status' => $status, 'per_page' => $perPage],
        'categories'     => ITJobCategory::orderBy('name')->get(['id', 'name']),
        // divisionChiefs and misPersonnel removed — now auto-resolved on store()
        'ictEquipment'   => ICTEquipment::with(['room:id,name,code', 'owner:id,name'])
            ->orderBy('description')
            ->get(['id', 'description', 'room_id', 'owner_id', 'serial_no']),
        'isAdmin'        => $isAdmin,
        'hasPin'         => ! empty($user->signature_pin),
        'signatureUri'   => $this->sigService->getSignatureDataUri($user),
    ]);
}



    private function getUserRole($user): string
    {
        return $user->getRoleName();
    }

    /**
     * Determine who must approve this request.
     *
     * Default: the Division Chief of the requestor's division.
     * Exception: "Posting to Website" / "Posting to Social Media" with posting_type = 'general'
     *            routes to the Information Officer instead of the DC.
     */
    private function resolveApprover(User $user, string $category, ?string $postingType): int
    {
        $postingCategories = ['Posting to Website', 'Posting to Social Media'];

        if (in_array($category, $postingCategories) && $postingType === 'general') {
            $io = User::havingRole('InformationOfficer')->first();
            if (! $io) {
                throw new \RuntimeException('No Information Officer is currently configured. Please contact your administrator.');
            }
            return $io->id;
        }

        // All other categories → user's Division Chief
        $chiefId = $user->division_id
            ? \App\Models\Division::where('id', $user->division_id)->value('division_chief_id')
            : null;

        if (! $chiefId) {
            throw new \RuntimeException('You are not assigned to a division with a Division Chief. Please contact HR.');
        }

        return $chiefId;
    }

    /**
     * Auto-assign MIS personnel based on category.
     * "Technical Assistance on Events" → Alexis Dave San Miguel (id=5).
     * All other categories → load-balance between Junlou (id=1) and Michael (id=2).
     */
    private function autoAssignMIS(string $category): ?int
    {
        if ($category === 'Technical Assistance on Events') {
            return 5;
        }

        $terminalStatuses = ['Request Completed', 'Rejected by Division Chief', 'Rejected by OCD'];
        $poolIds = collect([1, 2]);

        $activeCounts = ITJobRequest::whereIn('assignedto', $poolIds)
            ->whereNotIn('status', $terminalStatuses)
            ->groupBy('assignedto')
            ->selectRaw('assignedto, COUNT(*) as cnt')
            ->pluck('cnt', 'assignedto');

        return $poolIds->sortBy(fn ($id) => [$activeCounts->get($id, 0), $id])->first();
    }

    /* =====================================================
     | STORE
     |=====================================================*/
    public function store(Request $request)
    {
        $isTechEvent   = $request->input('category') === 'Technical Assistance on Events';
        $isPosting     = in_array($request->input('category'), ['Posting to Website', 'Posting to Social Media']);

        $validated = $request->validate([
            'category'     => 'required|string',
            'event_date'   => $isTechEvent
                ? 'required|date|after_or_equal:' . now()->addDays(3)->toDateString()
                : 'nullable|date',
            'posting_type' => $isPosting ? 'required|in:financial,general' : 'nullable',
            'title'        => 'required|string|max:255',
            'description'  => 'required|string',
            'priority'     => 'nullable|in:urgent,high,normal,low',
        ], [
            'event_date.required'       => 'The date of the event is required for Technical Assistance requests.',
            'event_date.after_or_equal' => 'Filing a Technical Assistance request less than 3 days before the event is not allowed.',
            'posting_type.required'     => 'Please specify whether this posting is Financial or General.',
        ]);

        // Auto-resolve approver (Division Chief or Information Officer)
        try {
            $validated['divisionchief_id'] = $this->resolveApprover(
                $request->user(),
                $validated['category'],
                $validated['posting_type'] ?? null
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['category' => $e->getMessage()]);
        }

        // Auto-assign MIS personnel (category-routed)
        $validated['assignedto'] = $this->autoAssignMIS($validated['category']);

        // Duplicate guard: same user submitted identical title+category within the last 30 seconds
        $isDuplicate = ITJobRequest::where('user_id', $request->user()->id)
            ->where('title', $validated['title'])
            ->where('category', $validated['category'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($isDuplicate) {
            return back()->withErrors(['title' => 'Duplicate request detected. Please wait before submitting again.']);
        }

        $jobRequest = DB::transaction(function () use ($validated, $request) {
            // Generate ITJR number (inside transaction to prevent race conditions)
            $prefix = now()->format('Y-m');
            $latestSeq = ITJobRequest::where('itjr_no', 'like', "{$prefix}-%")
                ->lockForUpdate()
                ->select(DB::raw("MAX(CAST(SUBSTRING_INDEX(itjr_no, '-', -1) AS UNSIGNED)) as seq"))
                ->value('seq');

            $validated['itjr_no'] = sprintf('%s-%04d', $prefix, ($latestSeq ?? 0) + 1);
            $validated['user_id'] = $request->user()->id;
            $validated['status'] = 'Pending Division Chief Approval';

            $jobRequest = ITJobRequest::create($validated);

            ITJRTrackingLog::create([
                'it_job_request_id' => $jobRequest->id,
                'status' => 'Submitted IT Job Request',
                'remarks' => 'Request submitted by user.',
                'updated_by' => $request->user()->id,
            ]);

            return $jobRequest;
        });

        // Send email + in-app notification to Division Chief
        if ($jobRequest->divisionchief_id) {
            $chief = User::find($jobRequest->divisionchief_id);
            if ($chief) {
                if ($chief->email) {
                    $approveUrl = URL::signedRoute('it-job-requests.dc.approve', ['jobRequest' => $jobRequest->id, 'chief' => $chief->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('it-job-requests.dc.decline', ['jobRequest' => $jobRequest->id, 'chief' => $chief->id], now()->addDays(7));
                    try {
                        Mail::to($chief->email)->send(new DivisionChiefITJRApprovalMail($jobRequest, $approveUrl, $declineUrl));
                    } catch (\Throwable $e) {
                        logger()->error('Failed to send Division Chief ITJR email', ['error' => $e->getMessage()]);
                    }
                }
                NotificationService::notifyUser($chief, 'IT Job Request', $jobRequest->itjr_no, 'New request awaiting your approval', route('jobrequests.index'));
            }
        }

        // Send email + in-app notification to Assigned Administrator
        if ($jobRequest->assignedto) {
            $admin = User::find($jobRequest->assignedto);
            if ($admin) {
                if ($admin->email) {
                    try {
                        Mail::to($admin->email)->send(new ITJRStatusMail($jobRequest, 'New Request Assigned', 'You have been assigned to this request.', 'Administrator'));
                    } catch (\Throwable $e) {
                        logger()->error('Failed to send Administrator ITJR email', ['error' => $e->getMessage()]);
                    }
                }
                NotificationService::notifyUser($admin, 'IT Job Request', $jobRequest->itjr_no, 'New request assigned to you', route('jobrequests.index'));
            }
        }

        // Optional digital signing on submission
        $this->trySign($request, $jobRequest, 'submission',
            "IT Job Request #{$jobRequest->itjr_no} — {$jobRequest->title}",
            $jobRequest->itjr_no . $jobRequest->title . $jobRequest->user_id
        );

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
        if ($jobRequest->user) {
            NotificationService::notifyUser(
                $jobRequest->user,
                'IT Job Request',
                $jobRequest->itjr_no,
                'Approved by Division Chief',
                route('jobrequests.index'),
            );
        }

        // Notify OCD users
        $ocdUsers = User::havingRole('OCD')->get();
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
        if ($jobRequest->user) {
            NotificationService::notifyUser($jobRequest->user, 'IT Job Request', $jobRequest->itjr_no, 'Rejected by Division Chief', route('jobrequests.index'), $validated['reason']);
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
    if (!$ocdUser || ! $ocdUser->hasRole('OCD')) {
        abort(403, 'Unauthorized OCD user.');
    }

    // 2️⃣ Check if the job request is waiting for OCD approval
    if ($jobRequest->status !== 'Pending OCD Approval') {
        return view('emails.itjr.already_approved', compact('jobRequest'));
    }

    // 3️⃣ Update the request status and log the approval date
    $jobRequest->update([
        'ocd_approval_date' => now(),
        'status'            => 'In Progress',
        'queued_at'         => now(),
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
    if ($jobRequest->user) {
        NotificationService::notifyUser(
            $jobRequest->user,
            'IT Job Request',
            $jobRequest->itjr_no,
            'Approved by OCD — now In Progress',
            route('jobrequests.index'),
        );
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
        if ($jobRequest->user) {
            NotificationService::notifyUser($jobRequest->user, 'IT Job Request', $jobRequest->itjr_no, 'Rejected by OCD', route('jobrequests.index'), $validated['reason']);
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
        'mis_assessment'           => 'nullable|string',
        'recommendation'           => 'nullable|string|max:2000',
        'expected_completion_date' => 'nullable|date',
        'action_taken'             => 'nullable|string',
        'completed_at'             => 'nullable|date',
        'ict_equipment_id'         => 'nullable|exists:ict_equipments,id',
    ]);

    $isActedByMIS = !empty($validated['action_taken']) || !empty($validated['completed_at']);

    $status = $isActedByMIS
        ? 'Acted by MIS'
        : 'MIS Assessed the Request';

    DB::transaction(function () use ($jobRequest, $validated, $status, $isActedByMIS, $request) {
        $jobRequest->update(array_merge($validated, [
            'status' => $status,
            'attendedby' => $request->user()->name,
        ]));

        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status' => $status,
            'remarks' => collect($validated)->filter()->implode("\n"),
            'updated_by' => $request->user()->id,
        ]);

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
    });

    // 📧 NOTIFY REQUESTER (outside transaction — email failures must not roll back DB changes)
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
    if ($jobRequest->user) {
        NotificationService::notifyUser(
            $jobRequest->user,
            'IT Job Request',
            $jobRequest->itjr_no,
            $isActedByMIS ? 'Acted by MIS — please confirm completion' : 'Assessed by MIS',
            route('jobrequests.index'),
        );
    }

    // Auto-generate PDF when status becomes "Acted by MIS"
    if ($isActedByMIS) {
        $this->trySign($request, $jobRequest, 'mis_acted',
            "IT Job Request #{$jobRequest->itjr_no} — Acted by MIS",
            $jobRequest->itjr_no . 'mis_acted' . $request->user()->id
        );

        try {
            $this->pdfService->generate($jobRequest);
        } catch (\Throwable $e) {
            logger()->error('Failed to generate IT Job Request PDF', [
                'job_request_id' => $jobRequest->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    return back()->with('success', 'MIS assessment saved.');
}


    public function confirmCompletion(Request $request, ITJobRequest $jobRequest)
    {
        // Idempotency: already completed — return early without duplicate entry
        if ($jobRequest->status === 'Request Completed') {
            return back()->with('success', 'Request already confirmed.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($jobRequest, $validated, $request) {
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
        });

        NotificationService::notifyUser($request->user(), 'IT Job Request', $jobRequest->itjr_no, 'Request Completed — thank you for your rating!', route('jobrequests.index'));

        $this->trySign($request, $jobRequest, 'completion',
            "IT Job Request #{$jobRequest->itjr_no} — Completion Confirmed",
            $jobRequest->itjr_no . 'completion' . $request->user()->id
        );

        return back()->with('success', 'Request confirmed and rated.');
    }

    /**
     * Create a digital signature for the completion stage.
     * Called separately from the CSM rating form so PIN can be collected first.
     */
    public function signCompletion(Request $request, ITJobRequest $jobRequest)
    {
        if ($jobRequest->user_id !== $request->user()->id) {
            abort(403);
        }

        $alreadySigned = DigitalSignature::where('signable_type', ITJobRequest::class)
            ->where('signable_id', $jobRequest->id)
            ->where('metadata->stage', 'completion')
            ->exists();

        if (! $alreadySigned) {
            $this->trySign(
                $request,
                $jobRequest,
                'completion',
                "IT Job Request #{$jobRequest->itjr_no} — Completion Confirmed",
                $jobRequest->itjr_no . 'completion' . $request->user()->id,
            );
        }

        return response()->json(['ok' => true]);
    }

    public function forApproval(Request $request)
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['DivisionChief', 'InformationOfficer'])) {
            abort(403, 'Unauthorized');
        }

        $search   = trim($request->query('search', ''));
        $category = trim($request->query('category', ''));
        $perPage  = min((int) $request->query('per_page', 15), 50);

        $requests = ITJobRequest::with('user:id,name')
            ->where('divisionchief_id', $user->id)
            ->where('status', 'Pending Division Chief Approval')
            ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhere('itjr_no', 'like', "%{$search}%")
                      ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->when($category, fn($q) => $q->where('category', $category))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ITJobRequests/ForApprovalITJR', [
            'requests'   => $requests,
            'filters'    => ['search' => $search, 'category' => $category],
            'categories' => ITJobCategory::orderBy('name')->get(['id', 'name']),
            'hasPin'     => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
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

            $this->snapshots->recordApproval(
                approvable: $jobRequest,
                step:       ApprovalStep::REQ_DIVISION_CHIEF,
                sequence:   1,
                action:     'approved',
                approver:   $request->user(),
            );

            $this->trySign($request, $jobRequest, 'dc_approval',
                "IT Job Request #{$jobRequest->itjr_no} — Division Chief Approval",
                $jobRequest->itjr_no . 'dc_approval' . $request->user()->id
            );
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

            $this->snapshots->recordApproval(
                approvable: $jobRequest,
                step:       ApprovalStep::REQ_DIVISION_CHIEF,
                sequence:   1,
                action:     'rejected',
                approver:   $request->user(),
            );
        }

        return back()->with('success', 'Division Chief action recorded!');
    }

    public function ocdApproval(Request $request)
    {
        $user = $request->user();

        if (! $user->hasPermission('it.requests.manage')) {
            abort(403, 'Unauthorized');
        }

        $search   = trim($request->query('search', ''));
        $category = trim($request->query('category', ''));
        $perPage  = min((int) $request->query('per_page', 15), 50);

        $requests = ITJobRequest::with('user:id,name')
            ->where('status', 'Pending OCD Approval')
            ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhere('itjr_no', 'like', "%{$search}%")
                      ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->when($category, fn($q) => $q->where('category', $category))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('ITJobRequests/OCDApprovalITJR', [
            'requests'   => $requests,
            'filters'    => ['search' => $search, 'category' => $category],
            'categories' => ITJobCategory::orderBy('name')->get(['id', 'name']),
            'hasPin'     => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    public function approveByOCD(Request $request, ITJobRequest $jobRequest)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
        ]);

        if ($request->action === 'approve') {
            $jobRequest->update([
                'ocd_approval'     => true,
                'ocd_approval_date' => now(),
                'status'           => 'In Progress',
                'queued_at'        => now(),
            ]);

            ITJRTrackingLog::create([
                'it_job_request_id' => $jobRequest->id,
                'status' => 'OCD Approved',
                'remarks' => 'Request Approved by OCD.',
                'updated_by' => $request->user()->id,
            ]);

            $this->snapshots->recordApproval(
                approvable: $jobRequest,
                step:       ApprovalStep::REQ_OCD,
                sequence:   4,
                action:     'approved',
                approver:   $request->user(),
            );

            $this->trySign($request, $jobRequest, 'ocd_approval',
                "IT Job Request #{$jobRequest->itjr_no} — OCD Approval",
                $jobRequest->itjr_no . 'ocd_approval' . $request->user()->id
            );
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

            $this->snapshots->recordApproval(
                approvable: $jobRequest,
                step:       ApprovalStep::REQ_OCD,
                sequence:   4,
                action:     'rejected',
                approver:   $request->user(),
            );
        }

        return back()->with('success', 'OCD action recorded!');
    }

    /* =====================================================
     | CHECK PENDING "ACTED BY MIS" FOR CURRENT USER
     |=====================================================*/
    public function checkPendingActedByMis(Request $request)
    {
        $count = ITJobRequest::where('user_id', $request->user()->id)
            ->where('status', 'Acted by MIS')
            ->count();

        return response()->json(['has_pending' => $count > 0, 'count' => $count]);
    }

    /* =====================================================
     | PRINT / DOWNLOAD PDF (single record)
     |=====================================================*/
    public function printForm(ITJobRequest $jobRequest)
    {
        return $this->pdfService->stream($jobRequest);
    }

    /* =====================================================
     | EXPORT PDF (list report)
     |=====================================================*/
    public function exportPdf(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->hasPermission('it.requests.manage');

        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        $category = $request->input('category', '');
        $scope    = $request->input('scope', 'mine');

        $query = ITJobRequest::with(['user', 'assignedTo'])
            ->whereIn('status', ['Acted by MIS', 'Request Completed'])
            ->orderBy('created_at');

        // Non-admin users see only requests they personally attended (attendedby name match).
        // Admin/MIS with scope=all see everything.
        if (! $isAdmin || $scope === 'mine') {
            $query->where('attendedby', $user->name);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($category) {
            $query->where('category', $category);
        }

        $records  = $query->get();
        $notedBy  = User::havingRole('OCD')->first();

        return $this->pdfService->exportList(
            records:     $records,
            preparedBy:  $user,
            notedBy:     $notedBy,
            dateFrom:    $dateFrom,
            dateTo:      $dateTo,
            category:    $category ?: null,
        );
    }

    /* =====================================================
     | DESTROY
     |=====================================================*/
    public function destroy(ITJobRequest $jobRequest)
    {
        DB::transaction(function () use ($jobRequest) {
            $jobRequest->trackingLogs()->delete();
            $jobRequest->delete();
        });

        return back()->with('success', 'IT Job Request deleted successfully.');
    }

    /* =====================================================
     | QUEUE DASHBOARD (MIS / Admin only)
     |=====================================================*/
    public function queue(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->hasPermission('it.requests.manage');

        if (! $isAdmin) {
            abort(403, 'Unauthorized');
        }

        $search   = trim($request->query('search', ''));
        $category = trim($request->query('category', ''));

        $items = ITJobRequest::select([
                'id', 'itjr_no', 'title', 'category', 'description', 'status',
                'user_id', 'assignedto', 'priority', 'queued_at', 'created_at',
                'mis_assessment', 'recommendation', 'expected_completion_date',
                'action_taken', 'completed_at', 'ict_equipment_id',
            ])
            ->with(['user:id,name', 'assignedTo:id,name'])
            ->inQueue()
            ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                      ->orWhere('itjr_no', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->when($category, fn($q) => $q->where('category', $category))
            ->get();

        // Assign absolute queue positions
        $items->each(function ($item, $index) {
            $item->queue_position = $index + 1;
        });

        return Inertia::render('ITJobRequests/Queue', [
            'items'        => $items,
            'filters'      => ['search' => $search, 'category' => $category],
            'categories'   => ITJobCategory::orderBy('name')->get(['id', 'name']),
            'ictEquipment' => ICTEquipment::with(['room:id,name,code', 'owner:id,name'])
                ->orderBy('description')
                ->get(['id', 'description', 'room_id', 'owner_id', 'serial_no']),
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    /* =====================================================
     | UPDATE PRIORITY (MIS / Admin only)
     |=====================================================*/
    public function updatePriority(Request $request, ITJobRequest $jobRequest)
    {
        $user    = $request->user();
        $isAdmin = $user->hasPermission('it.requests.manage');

        if (! $isAdmin) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'priority' => 'required|in:urgent,high,normal,low',
        ]);

        $oldPriority = $jobRequest->priority ?? 'normal';
        $newPriority = $validated['priority'];

        $jobRequest->update(['priority' => $newPriority]);

        ITJRTrackingLog::create([
            'it_job_request_id' => $jobRequest->id,
            'status'            => 'Priority Updated',
            'remarks'           => "Priority changed from {$oldPriority} to {$newPriority} by {$user->name}.",
            'updated_by'        => $user->id,
        ]);

        // Notify requestor only on escalation (priority goes up)
        $rankMap = ITJobRequest::PRIORITY_RANK;
        $wasEscalated = ($rankMap[$newPriority] ?? 3) < ($rankMap[$oldPriority] ?? 3);

        if ($wasEscalated && $jobRequest->user && $jobRequest->user->email) {
            try {
                Mail::to($jobRequest->user->email)->send(
                    new ITJRStatusMail(
                        $jobRequest,
                        'Request Priority Updated',
                        "Your request has been escalated to {$newPriority} priority.",
                        'MIS'
                    )
                );
            } catch (\Throwable $e) {
                logger()->error('Failed to send priority escalation email', [
                    'job_request_id' => $jobRequest->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'Priority updated.');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function trySign(
        Request      $request,
        ITJobRequest $jobRequest,
        string       $stage,
        string       $title,
        string       $contentHash
    ): void {
        $signer = $request->user();

        // Users who have set a PIN must provide the correct one to sign.
        // Users without a PIN are signed automatically (signature image in PDF).
        if (! empty($signer->signature_pin)) {
            $pin = $request->input('pin');
            if (! $pin || ! $this->sigService->verifyPin($signer, $pin)) {
                return;
            }
        }

        try {
            $this->sigService->sign(
                signer:        $signer,
                signableType:  ITJobRequest::class,
                signableId:    $jobRequest->id,
                documentTitle: $title,
                contentToHash: $contentHash,
                metadata:      [
                    'stage'   => $stage,
                    'itjr_no' => $jobRequest->itjr_no,
                    'title'   => $jobRequest->title,
                ],
            );
        } catch (\Throwable $e) {
            logger()->error('ITJR digital sign failed', [
                'job_request_id' => $jobRequest->id,
                'stage'          => $stage,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
