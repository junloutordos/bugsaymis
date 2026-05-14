<?php

namespace App\Http\Controllers\HR;

use App\Enums\ApprovalStep;
use App\Http\Controllers\Controller;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
use App\Models\HR\ServiceCreditRecord;
use App\Models\FacultyLoading\SalarySchedule;
use App\Services\HR\ApprovalService;
use App\Services\HR\LeaveCreditService;
use App\Services\SnapshotService;
use App\Services\DigitalSignatureService;
use App\Http\Traits\SignsDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaveApplicationController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private LeaveCreditService      $credits,
        private ApprovalService         $approvals,
        private SnapshotService         $snapshots,
        private DigitalSignatureService $sigService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('hr.leave.view');

        $query = LeaveApplication::with(['user', 'leaveType'])
            ->orderByDesc('filed_at');

        // Regular employees only see their own
        if (! Auth::user()->hasAnyPermission(['hr.leave.approve', 'hr.employee.manage'])) {
            $query->where('user_id', Auth::id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return Inertia::render('HR/Leave/Index', [
            'applications' => $query->paginate(20)->withQueryString(),
            'leaveTypes'   => LeaveType::where('is_active', true)->orderBy('sort_order')->get(),
            'filters'      => $request->only('status'),
        ]);
    }

    public function create()
    {
        $this->authorize('hr.leave.file');

        $balances = $this->credits->getEmployeeLeaveBalance(Auth::id(), now()->year);

        $authUser = Auth::user();

        return Inertia::render('HR/Leave/Create', [
            'leaveTypes'   => LeaveType::where('is_active', true)->orderBy('sort_order')->get(),
            'credits'      => LeaveCredit::where('user_id', Auth::id())
                ->where('year', now()->year)
                ->with('leaveType')
                ->get(),
            'balances'     => $balances,
            'hasPin'       => ! empty($authUser->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($authUser),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('hr.leave.file');

        $data = $request->validate([
            'leave_type_id'         => 'required|exists:leave_types,id',
            'dates'                 => 'required|array|min:1',
            'dates.*'               => 'required|date',
            'leave_details'         => 'nullable|string',
            'leave_details_specify' => 'nullable|string|max:255',
            'reason'                => 'nullable|string|max:1000',
            'supporting_document'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Sort dates and derive date_from / date_to for range queries
        $sortedDates       = collect($data['dates'])->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString())->sort()->values();
        $data['date_from'] = $sortedDates->first();
        $data['date_to']   = $sortedDates->last();
        $data['dates']     = $sortedDates->all();

        $leaveType  = LeaveType::findOrFail($data['leave_type_id']);
        $days       = $this->computeWorkingDaysFromDates($data['dates']);
        $user       = Auth::user();

        // ── Balance validation before filing ──────────────────────────────────
        // Only validate for deductible leave types with a tracked credit balance
        if ($leaveType->is_deductible) {
            $balances = $this->credits->getEmployeeLeaveBalance($user->id, now()->year);
            $balance  = $balances[$leaveType->code]['balance'] ?? 0;

            if ($days > $balance) {
                // Do not block filing — CSC allows LWOP; surface warning instead
                // The is_without_pay flag will be set upon approval deduction
                session()->flash('credit_warning',
                    "Your {$leaveType->code} balance is {$balance} day(s) but you are applying for {$days} day(s). "
                    . "Days in excess of your balance will be treated as Leave Without Pay (LWOP)."
                );
            }
        }

        $data['user_id']      = $user->id;
        $data['days_applied'] = $days;
        $data['status']       = 'pending';

        if ($request->hasFile('supporting_document')) {
            $data['supporting_document'] = $request->file('supporting_document')
                ->store('hr/leave_documents', 'local');
        }

        $leaveApplication = LeaveApplication::create($data);

        $this->performSign($request, LeaveApplication::class, $leaveApplication->id,
            'submission',
            "Leave Application #{$leaveApplication->id}",
            LeaveApplication::class . $leaveApplication->id . 'submission'
        );

        return redirect()->route('hr.leave.index')->with('success', 'Leave application filed.');
    }

    public function show(LeaveApplication $leaveApplication)
    {
        $this->authorize('hr.leave.view');

        return Inertia::render('HR/Leave/Show', [
            'application' => $leaveApplication->load([
                'user', 'leaveType',
                'hrOfficer',       // Stage 1
                'divisionChief',   // Stage 2
                'approvedBy',      // Stage 3
            ]),
        ]);
    }

    /**
     * 3-stage approval workflow:
     *   Stage 1 — hr_officer   : HR Officer certifies leave credits  (pending      → hr_verified)
     *   Stage 2 — division_chief: Division Chief recommends           (hr_verified  → forwarded)
     *   Stage 3 — campus_director: Campus Director final approval     (forwarded    → approved/rejected)
     */
    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        $this->authorize('hr.leave.approve');

        $data = $request->validate([
            'stage'   => 'required|in:hr_officer,division_chief,campus_director',
            'action'  => 'required|string',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->approvals->processLeave(
            application: $leaveApplication,
            stage:       $data['stage'],
            action:      $data['action'],
            remarks:     $data['remarks'] ?? '',
            approver:    Auth::user(),
        );

        if ($data['action'] === 'approve') {
            $this->performSign($request, LeaveApplication::class, $leaveApplication->id,
                $data['stage'],
                "Leave Application #{$leaveApplication->id}",
                LeaveApplication::class . $leaveApplication->id . $data['stage']
            );
        }

        return back()->with('success', 'Action recorded.');
    }

    public function cancel(LeaveApplication $leaveApplication)
    {
        if ($leaveApplication->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($leaveApplication->status, ['pending', 'hr_verified', 'forwarded', 'approved'])) {
            return back()->with('error', 'This application cannot be cancelled.');
        }

        DB::transaction(function () use ($leaveApplication) {
            // Restore credits if the leave was already approved and credits were deducted
            if ($leaveApplication->status === 'approved') {
                $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
            }

            $leaveApplication->update(['status' => 'cancelled']);
        });

        return back()->with('success', 'Application cancelled.');
    }

    // ── Employee Credit Dashboard ─────────────────────────────────────────────

    /**
     * GET /hr/leave-credits/my
     * Personal leave credit balances + deduction history for the logged-in user.
     */
    public function myCredits(Request $request)
    {
        $this->authorize('hr.leave.credits.view');

        $userId = Auth::id();
        $year   = (int) $request->input('year', now()->year);

        $balances = $this->credits->getEmployeeLeaveBalance($userId, $year);

        // Full credit rows for the breakdown table
        $creditRows = LeaveCredit::with('leaveType')
            ->where('user_id', $userId)
            ->where('year', $year)
            ->get();

        // Transaction ledger for the year
        $transactions = \App\Models\HR\LeaveCreditTransaction::with(['leaveType', 'recorder'])
            ->where('user_id', $userId)
            ->where('year', $year)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Leave applications for the year with deduction info
        $applications = \App\Models\HR\LeaveApplication::with('leaveType')
            ->where('user_id', $userId)
            ->whereYear('date_from', $year)
            ->orderByDesc('date_from')
            ->get(['id', 'control_no', 'leave_type_id', 'date_from', 'date_to',
                   'days_applied', 'days_deducted', 'is_without_pay', 'status']);

        $isTeaching = $this->credits->isTeaching(Auth::user());

        // Service credit records (Teaching only) — all statuses, all years
        $serviceRecords = $isTeaching
            ? ServiceCreditRecord::where('user_id', $userId)
                ->orderByDesc('service_date')
                ->get(['id', 'service_date', 'service_type', 'hours_rendered',
                       'days_equivalent', 'status', 'expires_at', 'remarks',
                       'approved_at'])
                ->toArray()
            : [];

        return Inertia::render('HR/Leave/MyCredits', [
            'balances'       => $balances,
            'creditRows'     => $creditRows,
            'transactions'   => $transactions,
            'applications'   => $applications,
            'serviceRecords' => $serviceRecords,
            'year'           => $year,
            'years'          => range(now()->year, max(now()->year - 5, 2024)),
            'isTeaching'     => $isTeaching,
        ]);
    }

    /**
     * POST /leave-credits/my/service-credits
     * Faculty submits a service credit earning record (status = pending).
     */
    public function myServiceCreditsStore(Request $request)
    {
        $this->authorize('hr.leave.credits.view');

        $user = Auth::user();
        if (! $this->credits->isTeaching($user)) {
            abort(403, 'Service credits are for Teaching personnel only.');
        }

        $data = $request->validate([
            'service_date'   => 'required|date|before_or_equal:today',
            'service_type'   => 'required|in:extra_teaching_load,committee_work,school_activity,special_assignment,other',
            'hours_rendered' => 'required|numeric|min:4|max:24',
            'remarks'        => 'nullable|string|max:500',
        ]);

        try {
            $this->credits->addServiceCredits(
                userId:        Auth::id(),
                hoursRendered: (float) $data['hours_rendered'],
                serviceType:   $data['service_type'],
                serviceDate:   $data['service_date'],
                remarks:       $data['remarks'] ?? '',
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Service credit record submitted and is pending HR approval.');
    }

    // ── Real-time balance check (AJAX) ────────────────────────────────────────

    /**
     * Return the current leave balance for the authenticated user.
     * Called by the Leave Create/Edit form via fetch() to show a live indicator.
     *
     * GET /hr/leave/balance?leave_type_id=1&days=3
     */
    public function checkBalance(Request $request)
    {
        $this->authorize('hr.leave.file');

        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'days'          => 'required|numeric|min:0.5',
        ]);

        $leaveType = LeaveType::findOrFail($request->leave_type_id);
        $balances  = $this->credits->getEmployeeLeaveBalance(Auth::id(), now()->year);
        $entry     = $balances[$leaveType->code] ?? null;
        $balance   = $entry['balance'] ?? 0;
        $days      = (float) $request->days;

        return response()->json([
            'leave_type'   => $leaveType->code,
            'balance'      => $balance,
            'days_applied' => $days,
            'sufficient'   => $balance >= $days,
            'lwop_days'    => max(0, $days - $balance),
        ]);
    }

    public function printForm(LeaveApplication $leaveApplication)
    {
        $this->authorize('hr.leave.view');

        if ($leaveApplication->user_id !== Auth::id()
            && ! Auth::user()->hasAnyPermission(['hr.leave.approve', 'hr.employee.manage'])) {
            abort(403);
        }

        $application = $leaveApplication->load(['user.division.divisionchief', 'leaveType', 'hrOfficer', 'divisionChief', 'approvedBy']);

        // Resolve monthly salary from salary schedule (salary_grade + salary_step on the user)
        $monthlySalary = null;
        $u = $application->user;
        if ($u && $u->salary_grade) {
            $row = SalarySchedule::where('is_current', true)
                ->where('salary_grade', $u->salary_grade)
                ->where('step', $u->salary_step ?? 1)
                ->first();
            if ($row) {
                $monthlySalary = number_format((float) $row->monthly_rate, 2);
            }
        }

        // Leave credits for the year of the application
        $year    = \Carbon\Carbon::parse($application->date_from)->year;
        $credits = LeaveCredit::where('user_id', $application->user_id)
            ->where('year', $year)
            ->with('leaveType')
            ->get();

        $creditsMap = [];
        foreach ($credits as $c) {
            $code = strtoupper($c->leaveType?->code ?? '');
            if (! $code) {
                continue;
            }
            $balance = $c->balance ?? max(0,
                (float) $c->earned + (float) $c->carried_over
                - (float) $c->used - (float) $c->forfeited - (float) $c->monetized
            );
            $creditsMap[$code] = [
                'earned'  => round((float) $c->earned + (float) $c->carried_over, 3),
                'used'    => round((float) $c->used, 3),
                'balance' => round((float) $balance, 3),
            ];
        }

        // ── Resolve signatories (snapshot-first, live fallback) ──────────────

        // Fallback: live certifying officer (HR Officer who certified)
        $liveCertifying = $application->hr_officer_id ? $application->hrOfficer : null;

        // Fallback: live authorized officer (Division Chief of the applicant's division)
        $liveAuthorized = $application->user?->division?->divisionchief;
        if (! $liveAuthorized && $application->division_chief_id) {
            $liveAuthorized = $application->divisionChief;
        }

        // Fallback: live authorized official (OCD / Campus Director)
        $liveOfficial = null;
        $ocdDiv = DB::table('divisions')
            ->where('division_name', 'like', '%Campus Director%')
            ->orWhere('acronym', 'OCD')
            ->first();
        if ($ocdDiv?->division_chief_id) {
            $liveOfficial = \App\Models\User::find($ocdDiv->division_chief_id);
        }
        if (! $liveOfficial && $application->approved_by) {
            $liveOfficial = $application->approvedBy;
        }

        // Capture signatories into snapshots (idempotent — safe to call every print)
        $this->snapshots->captureSignatories($application, [
            ['role_label' => ApprovalStep::SIG_CERTIFYING_OFFICER,  'user' => $liveCertifying],
            ['role_label' => ApprovalStep::SIG_AUTHORIZED_OFFICER,  'user' => $liveAuthorized],
            ['role_label' => ApprovalStep::SIG_AUTHORIZED_OFFICIAL, 'user' => $liveOfficial],
        ]);

        // Resolve final display data from snapshots (with live fallback)
        $certifyingOfficer  = $this->snapshots->resolveSignatory(
            $application, ApprovalStep::SIG_CERTIFYING_OFFICER, $liveCertifying
        );
        $authorizedOfficer  = $this->snapshots->resolveSignatory(
            $application, ApprovalStep::SIG_AUTHORIZED_OFFICER, $liveAuthorized
        );
        $authorizedOfficial = $this->snapshots->resolveSignatory(
            $application, ApprovalStep::SIG_AUTHORIZED_OFFICIAL, $liveOfficial
        );

        $sigs = $this->loadSigsForPrint(LeaveApplication::class, $leaveApplication->id);

        return Inertia::render('HR/Leave/PrintForm', [
            'application'        => $application,
            'credits'            => $creditsMap,
            'certifyingOfficer'  => $certifyingOfficer,
            'authorizedOfficer'  => $authorizedOfficer,
            'authorizedOfficial' => $authorizedOfficial,
            'monthlySalary'      => $monthlySalary,
            'sigs'               => $sigs,
        ]);
    }

    private function computeWorkingDaysFromDates(array $dates): float
    {
        $days = 0;
        foreach ($dates as $d) {
            $carbon = \Carbon\Carbon::parse($d);
            if (! $carbon->isWeekend()) {
                $days++;
            }
        }
        return $days;
    }

    private function computeWorkingDays(string $from, string $to): float
    {
        $start = \Carbon\Carbon::parse($from);
        $end   = \Carbon\Carbon::parse($to);
        $days  = 0;

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }
            $days++;
        }

        return $days;
    }
}
