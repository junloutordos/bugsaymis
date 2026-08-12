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

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('control_no', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('date_from', 'like', "%{$search}%")
                    ->orWhere('date_to', 'like', "%{$search}%")
                    ->orWhere('filed_at', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('leaveType', function ($typeQuery) use ($search) {
                        $typeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        return Inertia::render('HR/Leave/Index', [
            'applications' => $query->paginate(20)->withQueryString(),
            'leaveTypes'   => LeaveType::where('is_active', true)->orderBy('sort_order')->get(),
            'filters'      => $request->only('status', 'search'),
        ]);
    }

    public function create()
    {
        $this->authorize('hr.leave.file');

        $balances = $this->credits->getEmployeeLeaveBalance(Auth::id(), now()->year);

        $authUser = Auth::user();

        return Inertia::render('HR/Leave/Create', [
            'leaveTypes'     => LeaveType::where('is_active', true)->orderBy('sort_order')->get(),
            'credits'        => LeaveCredit::where('user_id', Auth::id())
                ->where('year', now()->year)
                ->with('leaveType')
                ->get(),
            'balances'       => $balances,
            'hasPin'         => ! empty($authUser->signature_pin),
            'signatureUri'   => $this->sigService->getSignatureDataUri($authUser),
            // Employment type (permanent/casual/contractual/coterminous/substitute), when on
            // file, is used by the frontend to grey out (never hide/block) leave types that
            // don't list the employee's type in applicable_employment_types.
            'employmentType' => $authUser->employeeProfile?->employment_type,
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

        $authUser = Auth::user();
        $ipcrWorkflow = app(\App\Services\PerformanceManagement\IPCRWorkflowService::class);
        $requiresAuh = $leaveApplication->user
            ? $ipcrWorkflow->requiresLeaveAuhRecommendation($leaveApplication->user)
            : false;

        return Inertia::render('HR/Leave/Show', [
            'application' => $leaveApplication->load([
                'user', 'leaveType',
                'hrOfficer',        // Stage 1
                'academicUnitHead', // Stage 2 (CID teaching faculty only)
                'divisionChief',    // Stage 3
                'approvedBy',       // Stage 4
            ]),
            'hasPin'       => ! empty($authUser->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($authUser),
            'requiresAuh'  => $requiresAuh,
            // Which signing stages this application has already committed to
            // (action recorded, status advanced) but have no matching
            // DigitalSignature record — lets the page offer a "Re-sign"
            // affordance instead of leaving the gap invisible until print.
            'missingSignatureStages' => $this->missingSignatureStages($leaveApplication),
            // Lets the "Review Application" button show for the specific
            // resolved recommender even when they hold no RBAC permission
            // for it (e.g. ACIDAA recommending their own leave).
            'isAuhRecommender' => $requiresAuh
                && ($recommender = $ipcrWorkflow->leaveRecommenderFor($leaveApplication->user))
                && (int) $recommender->id === (int) $authUser->id,
            'eligibleSubstitutes' => \App\Models\User::employees()
                ->where('id', '!=', $leaveApplication->user_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Stages that have already recorded an action (their *_id/*_action
     * columns are set) but have no matching DigitalSignature row — e.g. the
     * signer's PIN failed silently before the fix in approve() that now
     * blocks the transition on a bad PIN. Lets the page offer "Re-sign"
     * for already-affected applications instead of leaving the gap
     * invisible until someone prints the form.
     */
    private function missingSignatureStages(LeaveApplication $leaveApplication): array
    {
        $stageActedColumns = [
            'hr_officer'         => 'hr_officer_id',
            'academic_unit_head' => 'auh_id',
            'division_chief'     => 'division_chief_id',
            'campus_director'    => 'approved_by',
        ];

        $signedStages = \App\Models\DigitalSignature::where('signable_type', LeaveApplication::class)
            ->where('signable_id', $leaveApplication->id)
            ->get()
            ->map(fn ($sig) => $sig->metadata['stage'] ?? null)
            ->filter()
            ->all();

        $missing = [];
        foreach ($stageActedColumns as $stage => $column) {
            if ($leaveApplication->{$column} && ! in_array($stage, $signedStages, true)) {
                $missing[] = $stage;
            }
        }

        return $missing;
    }

    /**
     * Leave approval workflow (4 stages for CID teaching faculty, 3 for everyone else):
     *   Stage 1 — hr_officer        : HR Officer certifies leave credits    (pending       → hr_verified)
     *   Stage 2 — academic_unit_head: AUH (or CID Chief for an AUH's own leave)
     *                                 recommends — CID teaching faculty only (hr_verified   → auh_verified)
     *   Stage 3 — division_chief    : Division Chief recommends              (hr_verified/auh_verified → forwarded)
     *   Stage 4 — campus_director   : Campus Director final approval         (forwarded     → approved/rejected)
     */
    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        $data = $request->validate([
            'stage'   => 'required|in:hr_officer,academic_unit_head,division_chief,campus_director',
            'action'  => 'required|string',
            'remarks' => 'nullable|string|max:500',
        ]);

        // The academic_unit_head stage is authorized purely by identity (must
        // be the applicant's specific resolved recommender, checked below) —
        // an ACIDAA holder recommending their own leave has no dedicated RBAC
        // role/permission, mirroring how ApprovalInboxController already
        // treats ACIDAA designation membership as sufficient on its own.
        if ($data['stage'] !== 'academic_unit_head') {
            $this->authorize('hr.leave.approve');
        }

        $approver = Auth::user();

        // Nobody may act on their own leave application at any stage — even if
        // they separately hold HR Officer/AUH/Division Chief/Campus Director permission.
        abort_if(
            $leaveApplication->user_id === $approver->id,
            403,
            'You cannot act on your own leave application.'
        );

        // Academic Unit Head (or CID Chief, for an AUH's own leave) may only
        // act on the specific applicant they are the resolved recommender for.
        if ($data['stage'] === 'academic_unit_head' && ! $approver->isSuperAdmin()) {
            $ipcrWorkflow = app(\App\Services\PerformanceManagement\IPCRWorkflowService::class);
            $recommender = $leaveApplication->user
                ? $ipcrWorkflow->leaveRecommenderFor($leaveApplication->user)
                : null;
            abort_unless(
                $recommender && (int) $recommender->id === (int) $approver->id,
                403,
                'You are not the Academic Unit Head recommender for this employee.'
            );
        }

        // Division Chiefs may only act on leave from their own division
        if ($data['stage'] === 'division_chief' && ! $approver->isSuperAdmin()) {
            $divisionIds = \App\Models\Division::where('division_chief_id', $approver->id)
                ->pluck('id');
            abort_unless(
                $divisionIds->contains($leaveApplication->user?->division_id),
                403,
                'You can only act on leave applications from employees in your division.'
            );
        }

        $isSigningAction = in_array($data['action'], ['certified', 'recommended', 'forwarded', 'approved'], true);

        // Verify the signature PIN BEFORE committing the stage transition —
        // otherwise a wrong/missing PIN silently leaves the application
        // advanced (e.g. status='hr_verified') with no matching
        // DigitalSignature record, and nobody finds out until printing.
        if ($isSigningAction && ! $this->canSign($request)) {
            return back()->withErrors([
                'pin' => 'Incorrect signature PIN. This action was not recorded — please try again.',
            ]);
        }

        $this->approvals->processLeave(
            application: $leaveApplication,
            stage:       $data['stage'],
            action:      $data['action'],
            remarks:     $data['remarks'] ?? '',
            approver:    $approver,
        );

        if ($isSigningAction) {
            $signed = $this->performSign($request, LeaveApplication::class, $leaveApplication->id,
                $data['stage'],
                "Leave Application #{$leaveApplication->id}",
                LeaveApplication::class . $leaveApplication->id . $data['stage']
            );

            if (! $signed) {
                // The stage transition already committed (processLeave() ran
                // in its own transaction) — the PIN passed canSign() above,
                // so this is an unexpected failure (e.g. signing service
                // error), not a wrong PIN. Surface it so HR knows to use the
                // "Re-sign" affordance rather than assuming it worked.
                logger()->error('Leave approval committed but digital signature failed', [
                    'leave_application_id' => $leaveApplication->id,
                    'stage'                => $data['stage'],
                ]);

                return back()->with('warning', 'Action recorded, but your digital signature could not be saved. Please use "Re-sign" on this application.');
            }
        }

        return back()->with('success', 'Action recorded.');
    }

    /**
     * Re-sign a stage that was already acted upon (its *_id/*_action columns
     * are set, so the workflow already advanced) but has no matching
     * DigitalSignature record — the historical gap left by the silent-PIN-
     * failure bug in approve() before it started blocking on a bad PIN.
     * Does NOT re-run processLeave() — the stage transition already
     * happened; this only creates the missing signature record.
     */
    public function resign(Request $request, LeaveApplication $leaveApplication)
    {
        $data = $request->validate([
            'stage' => 'required|in:hr_officer,academic_unit_head,division_chief,campus_director',
        ]);
        $stage = $data['stage'];

        $stageActedColumns = [
            'hr_officer'         => 'hr_officer_id',
            'academic_unit_head' => 'auh_id',
            'division_chief'     => 'division_chief_id',
            'campus_director'    => 'approved_by',
        ];

        $actedColumn = $stageActedColumns[$stage];
        abort_unless($leaveApplication->{$actedColumn}, 404, 'This stage has not been acted upon yet.');

        // Only the original signer for that stage may re-sign it — this is
        // filling a gap in their own past action, not a new approval.
        $originalSignerId = $leaveApplication->{$actedColumn};
        abort_unless(
            (int) $originalSignerId === (int) Auth::id() || Auth::user()->isSuperAdmin(),
            403,
            'Only the original signer for this stage can re-sign it.'
        );

        abort_if(
            in_array($stage, $this->missingSignatureStages($leaveApplication), true) === false,
            409,
            'This stage already has a signature on file.'
        );

        if (! $this->canSign($request)) {
            return back()->withErrors([
                'pin' => 'Incorrect signature PIN. Signature was not saved — please try again.',
            ]);
        }

        $signed = $this->performSign($request, LeaveApplication::class, $leaveApplication->id,
            $stage,
            "Leave Application #{$leaveApplication->id}",
            LeaveApplication::class . $leaveApplication->id . $stage
        );

        if (! $signed) {
            return back()->with('warning', 'Signature could not be saved. Please try again.');
        }

        return back()->with('success', 'Signature saved.');
    }

    public function cancel(LeaveApplication $leaveApplication)
    {
        if ($leaveApplication->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($leaveApplication->status, ['pending', 'hr_verified', 'auh_verified', 'forwarded', 'approved'])) {
            return back()->with('error', 'This application cannot be cancelled.');
        }

        DB::transaction(function () use ($leaveApplication) {
            // Restore credits if the leave was already approved and credits were deducted
            if ($leaveApplication->status === 'approved') {
                $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
            }

            app(\App\Services\HR\SubstitutionService::class)->revokeForCancelledAbsence($leaveApplication);

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

        $application = $leaveApplication->load(['user.division.divisionchief', 'leaveType', 'hrOfficer', 'academicUnitHead', 'divisionChief', 'approvedBy']);

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

        // Teaching staff: CTO is tracked via approved Service Credit records (in days)
        if ($u && $this->credits->isTeaching($u)) {
            $serviceDays = ServiceCreditRecord::active()
                ->where('user_id', $application->user_id)
                ->sum('days_equivalent');

            $creditsMap['CTO'] = [
                'earned'  => round((float) $serviceDays, 3),
                'used'    => 0,
                'balance' => round((float) $serviceDays, 3),
            ];
        }

        // ── Resolve signatories (snapshot-first, live fallback) ──────────────

        // Certification of Leave Credits (7.A) must show whoever actually
        // performed the HR certification (hr_officer_id / the hr_officer
        // digital signature record) — HR staff other than the designated
        // Human Resource Designate, or a shared "Human Resource Unit"
        // account, may perform this step. Falls back to the official
        // Human Resource Designate title only when no signer is resolved
        // (should not occur once printing is gated on 'approved' status).
        $hrSigner = $application->hr_officer_id ? $application->hrOfficer : null;
        $certifyingOfficer = $hrSigner
            ? [
                'name'           => $hrSigner->name,
                'position'       => $hrSigner->position ?? 'Human Resource Designate',
                'division'       => null,
                'office'         => null,
                'signature_path' => null,
                'captured_at'    => $application->hr_officer_at,
            ]
            : [
                'name'           => 'Flora Mae O. Tormento',
                'position'       => 'Human Resource Designate',
                'division'       => null,
                'office'         => null,
                'signature_path' => null,
                'captured_at'    => null,
            ];

        // Fallback: live Academic Unit Head recommender (CID teaching faculty
        // only — AUH for regular faculty, CID Chief for an AUH's own leave).
        $requiresAuh = $application->user
            ? app(\App\Services\PerformanceManagement\IPCRWorkflowService::class)
                ->requiresLeaveAuhRecommendation($application->user)
            : false;
        $liveAuh = null;
        if ($requiresAuh) {
            $liveAuh = $application->auh_id
                ? $application->academicUnitHead
                : app(\App\Services\PerformanceManagement\IPCRWorkflowService::class)
                    ->leaveRecommenderFor($application->user);
        }

        // Fallback: live authorized officer (Division Chief of the applicant's division)
        // Division Chiefs have no DC above them — leave this signatory blank.
        $liveAuthorized = null;
        if (! $application->user?->hasRole('DivisionChief')) {
            $liveAuthorized = $application->user?->division?->divisionchief;
            if (! $liveAuthorized && $application->division_chief_id) {
                $liveAuthorized = $application->divisionChief;
            }
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
        $signatoriesToCapture = [
            ['role_label' => ApprovalStep::SIG_AUTHORIZED_OFFICER,  'user' => $liveAuthorized],
            ['role_label' => ApprovalStep::SIG_AUTHORIZED_OFFICIAL, 'user' => $liveOfficial],
        ];
        if ($requiresAuh) {
            $signatoriesToCapture[] = ['role_label' => ApprovalStep::SIG_ACADEMIC_UNIT_HEAD, 'user' => $liveAuh];
        }
        $this->snapshots->captureSignatories($application, $signatoriesToCapture);

        // Resolve final display data from snapshots (with live fallback)
        $authorizedOfficer  = $this->snapshots->resolveSignatory(
            $application, ApprovalStep::SIG_AUTHORIZED_OFFICER, $liveAuthorized
        );
        $authorizedOfficial = $this->snapshots->resolveSignatory(
            $application, ApprovalStep::SIG_AUTHORIZED_OFFICIAL, $liveOfficial
        );
        $academicUnitHead   = $requiresAuh
            ? $this->snapshots->resolveSignatory($application, ApprovalStep::SIG_ACADEMIC_UNIT_HEAD, $liveAuh)
            : null;

        $sigs = $this->loadSigsForPrint(LeaveApplication::class, $leaveApplication->id);

        $verifyUrl = route('document.verify.doc', ['type' => 'leave', 'id' => $leaveApplication->id]);
        $qrSvg     = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->margin(1)->generate($verifyUrl));

        return Inertia::render('HR/Leave/PrintForm', [
            'application'        => $application,
            'credits'            => $creditsMap,
            'certifyingOfficer'  => $certifyingOfficer,
            'academicUnitHead'   => $academicUnitHead,
            'authorizedOfficer'  => $authorizedOfficer,
            'authorizedOfficial' => $authorizedOfficial,
            'monthlySalary'      => $monthlySalary,
            'sigs'               => $sigs,
            'qrSvg'              => $qrSvg,
            'verifyUrl'          => $verifyUrl,
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
