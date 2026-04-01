<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveApplication;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveType;
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
            'date_from'             => 'required|date|after_or_equal:today',
            'date_to'               => 'required|date|after_or_equal:date_from',
            'leave_details'         => 'nullable|string',
            'leave_details_specify' => 'nullable|string|max:255',
            'reason'                => 'nullable|string|max:1000',
            'supporting_document'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $leaveType  = LeaveType::findOrFail($data['leave_type_id']);
        $days       = $this->computeWorkingDays($data['date_from'], $data['date_to']);
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
            'application' => $leaveApplication->load(['user', 'leaveType', 'divisionChief', 'approvedBy']),
        ]);
    }

    public function approve(Request $request, LeaveApplication $leaveApplication)
    {
        $this->authorize('hr.leave.approve');

        $data = $request->validate([
            'action'  => 'required|in:approved,rejected,forwarded',
            'remarks' => 'nullable|string|max:500',
            'stage'   => 'required|in:division_chief,hr',
        ]);

        DB::transaction(function () use ($data, $leaveApplication) {
            if ($data['stage'] === 'division_chief') {
                $leaveApplication->update([
                    'division_chief_id'      => Auth::id(),
                    'division_chief_action'  => $data['action'],
                    'division_chief_at'      => now(),
                    'division_chief_remarks' => $data['remarks'],
                    'status'                 => $data['action'] === 'forwarded' ? 'forwarded' : $data['action'],
                ]);

                // Restoration when DC rejects a previously-forwarded application
                if ($data['action'] === 'rejected') {
                    $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
                }
            } else {
                // HR final action
                $leaveApplication->update([
                    'approved_by'      => Auth::id(),
                    'approval_action'  => $data['action'],
                    'approved_at'      => now(),
                    'approval_remarks' => $data['remarks'],
                    'status'           => $data['action'],
                ]);

                if ($data['action'] === 'approved') {
                    // Deduct credits; marks is_without_pay automatically if balance insufficient
                    $this->credits->applyLeaveDeduction($leaveApplication->id, Auth::id());
                } elseif ($data['action'] === 'rejected') {
                    // Restore any credits that were deducted at an earlier stage
                    $this->credits->restoreLeaveCredits($leaveApplication->id, Auth::id());
                }
            }
        });

        return back()->with('success', 'Action recorded.');
    }

    public function cancel(LeaveApplication $leaveApplication)
    {
        if ($leaveApplication->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($leaveApplication->status, ['pending', 'forwarded', 'approved'])) {
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

        $application = $leaveApplication->load(['user', 'leaveType', 'divisionChief', 'approvedBy']);

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

        // Certifying officer — division chief who reviewed (or first approver)
        $certifyingOfficer = null;
        if ($application->division_chief_id && $application->divisionChief) {
            $certifyingOfficer = [
                'name'     => $application->divisionChief->name,
                'position' => $application->divisionChief->position ?? 'Authorized Officer',
            ];
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
            'authorizedOfficial' => $authorizedOfficial,
        ]);
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
            // TODO Phase 2: exclude public holidays from holidays table
            $days++;
        }

        return $days;
    }
}
