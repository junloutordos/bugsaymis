<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
use App\Models\FacultyLoading\SalarySchedule;
use App\Services\HR\LeaveCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeaveApplicationController extends Controller
{
    public function __construct(private LeaveCreditService $credits) {}

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

        return Inertia::render('HR/Leave/Create', [
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('sort_order')->get(),
            // Full credit rows for the print form sidebar
            'credits'    => LeaveCredit::where('user_id', Auth::id())
                ->where('year', now()->year)
                ->with('leaveType')
                ->get(),
            // Summarised balances keyed by leave type code for the balance indicator
            'balances'   => $balances,
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

        LeaveApplication::create($data);

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

        DB::transaction(function () use ($data, $leaveApplication) {
            switch ($data['stage']) {

                case 'hr_officer':
                    // Valid actions: certified | rejected
                    abort_unless(in_array($data['action'], ['certified', 'rejected']), 422);
                    $leaveApplication->update([
                        'hr_officer_id'      => Auth::id(),
                        'hr_officer_action'  => $data['action'],
                        'hr_officer_at'      => now(),
                        'hr_officer_remarks' => $data['remarks'],
                        'status'             => $data['action'] === 'certified' ? 'hr_verified' : 'rejected',
                    ]);
                    break;

                case 'division_chief':
                    // Valid actions: forwarded | rejected
                    abort_unless(in_array($data['action'], ['forwarded', 'rejected']), 422);
                    $leaveApplication->update([
                        'division_chief_id'      => Auth::id(),
                        'division_chief_action'  => $data['action'],
                        'division_chief_at'      => now(),
                        'division_chief_remarks' => $data['remarks'],
                        'status'                 => $data['action'],
                    ]);
                    if ($data['action'] === 'rejected') {
                        $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
                    }
                    break;

                case 'campus_director':
                    // Valid actions: approved | rejected
                    abort_unless(in_array($data['action'], ['approved', 'rejected']), 422);
                    $leaveApplication->update([
                        'approved_by'      => Auth::id(),
                        'approval_action'  => $data['action'],
                        'approved_at'      => now(),
                        'approval_remarks' => $data['remarks'],
                        'status'           => $data['action'],
                    ]);
                    if ($data['action'] === 'approved') {
                        $this->credits->applyLeaveDeduction($leaveApplication->id, Auth::id());
                    } else {
                        $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
                    }
                    break;
            }
        });

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

        return Inertia::render('HR/Leave/MyCredits', [
            'balances'     => $balances,
            'creditRows'   => $creditRows,
            'transactions' => $transactions,
            'applications' => $applications,
            'year'         => $year,
            'years'        => range(now()->year, max(now()->year - 5, 2024)),
            'isTeaching'   => $this->credits->isTeaching(Auth::user()),
        ]);
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

        // Certifying officer (7.A) — HR Officer who certified the leave credits
        $certifyingOfficer = null;
        if ($application->hr_officer_id && $application->hrOfficer) {
            $certifyingOfficer = [
                'name'     => $application->hrOfficer->name,
                'position' => $application->hrOfficer->position ?? 'Human Resource Officer',
            ];
        }

        // Authorized Officer (7.B) — Division Chief of the division the user belongs to
        $authorizedOfficer = null;
        $divChief = $application->user?->division?->divisionchief;
        if ($divChief) {
            $authorizedOfficer = [
                'name'     => $divChief->name,
                'position' => $divChief->position ?? 'Division Chief',
            ];
        } elseif ($certifyingOfficer) {
            // fallback: use the application's reviewing officer
            $authorizedOfficer = $certifyingOfficer;
        }

        // Authorized official — OCD / Campus Director
        $authorizedOfficial = null;
        $ocdDiv = DB::table('divisions')
            ->where('division_name', 'like', '%Campus Director%')
            ->orWhere('acronym', 'OCD')
            ->first();
        if ($ocdDiv?->division_chief_id) {
            $official = \App\Models\User::find($ocdDiv->division_chief_id);
            if ($official) {
                $authorizedOfficial = [
                    'name'     => $official->name,
                    'position' => $official->position ?? 'OIC - Campus Director',
                ];
            }
        }

        return Inertia::render('HR/Leave/PrintForm', [
            'application'        => $application,
            'credits'            => $creditsMap,
            'certifyingOfficer'  => $certifyingOfficer,
            'authorizedOfficer'  => $authorizedOfficer,
            'authorizedOfficial' => $authorizedOfficial,
            'monthlySalary'      => $monthlySalary,
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
