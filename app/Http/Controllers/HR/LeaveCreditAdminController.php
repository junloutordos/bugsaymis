<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveCreditTransaction;
use App\Models\HR\LeaveType;
use App\Models\HR\ServiceCreditRecord;
use App\Models\User;
use App\Services\HR\LeaveCreditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LeaveCreditAdminController extends Controller
{
    public function __construct(private LeaveCreditService $credits) {}

    // ── Credit Initialization ─────────────────────────────────────────────────

    /**
     * GET /hr/leave-credits/initialize
     * Show the opening-balance entry form.
     */
    public function initializeIndex(Request $request)
    {
        $this->authorize('hr.leave.credits.manage');

        $query = User::whereNotNull('emp_category')
            ->select('id', 'name', 'badge_id', 'position', 'emp_category')
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('badge_id', 'like', '%' . $request->search . '%');
            });
        }

        return Inertia::render('HR/Leave/Credits/Initialize', [
            'employees'       => $query->paginate(20)->withQueryString(),
            'leaveTypes'      => LeaveType::where('is_active', true)
                ->whereIn('code', ['VL', 'SL', 'CTO', 'WL'])
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name']),
            'filters'         => $request->only('search'),
            'specialChiefIds' => $this->credits->specialDivisionChiefIds(),
        ]);
    }

    /**
     * POST /hr/leave-credits/initialize
     * Save opening balances for one employee.
     */
    public function initializeStore(Request $request)
    {
        $this->authorize('hr.leave.credits.manage');

        $data = $request->validate([
            'user_id'  => 'required|exists:users,id',
            'year'     => 'required|integer|min:2000|max:2100',
            'balances' => 'required|array|min:1',
            'balances.*.leave_type_code' => 'required|string',
            'balances.*.amount'          => 'required|numeric|min:0',
            'remarks'  => 'required|string|max:500',
            'force'    => 'boolean',
            // Optional service credit opening balance (Teaching only — days, not hours)
            'sc_balance' => 'nullable|numeric|min:0.5|max:999',
        ]);

        $values = collect($data['balances'])
            ->filter(fn ($b) => $b['amount'] > 0)
            ->pluck('amount', 'leave_type_code')
            ->all();

        $hasLeaveCredits  = ! empty($values);
        $hasServiceCredit = isset($data['sc_balance']) && $data['sc_balance'] > 0;

        if (! $hasLeaveCredits && ! $hasServiceCredit) {
            return back()->with('error', 'Enter at least one leave balance or a service credit record.');
        }

        try {
            if ($hasLeaveCredits) {
                $this->credits->initializeLeaveCredits(
                    userId:     $data['user_id'],
                    values:     $values,
                    remarks:    $data['remarks'],
                    year:       $data['year'],
                    force:      $data['force'] ?? false,
                    recordedBy: Auth::id(),
                );
            }

            if ($hasServiceCredit) {
                $user = User::findOrFail($data['user_id']);
                if (! in_array($user->emp_category, ['Plantilla Teaching', 'COS Teaching'])) {
                    return back()->with('error', 'Service credits are for Teaching personnel only.');
                }

                $days      = (float) $data['sc_balance'];
                $yearStart = Carbon::create($data['year'], 1, 1)->toDateString();

                // Created directly as approved — opening balance, no pending stage
                ServiceCreditRecord::create([
                    'user_id'         => $data['user_id'],
                    'service_date'    => $yearStart,
                    'service_type'    => 'other',
                    'hours_rendered'  => $days * 8,
                    'days_equivalent' => $days,
                    'status'          => 'approved',
                    'approved_by'     => Auth::id(),
                    'approved_at'     => now()->toDateString(),
                    'expires_at'      => Carbon::create($data['year'], 12, 31)->toDateString(),
                    'remarks'         => 'Opening balance — ' . $data['year'] . '. ' . ($data['remarks'] ?? ''),
                ]);
            }
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Opening balances saved successfully.');
    }

    // ── Credit Adjustment ─────────────────────────────────────────────────────

    /**
     * GET /hr/leave-credits/adjust
     * Show the manual adjustment form with employee search.
     */
    public function adjustIndex(Request $request)
    {
        $this->authorize('hr.leave.credits.manage');

        $selectedUser = null;
        $currentCredits = [];

        if ($request->filled('user_id')) {
            $selectedUser = User::findOrFail($request->user_id);
            $currentCredits = $this->credits->getEmployeeLeaveBalance(
                $selectedUser->id,
                $request->input('year', now()->year)
            );
        }

        $query = User::whereNotNull('emp_category')
            ->select('id', 'name', 'badge_id', 'position', 'emp_category')
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('badge_id', 'like', '%' . $request->search . '%');
            });
        }

        return Inertia::render('HR/Leave/Credits/Adjust', [
            'employees'      => $query->paginate(15)->withQueryString(),
            'leaveTypes'     => LeaveType::where('is_active', true)
                ->whereIn('code', ['VL', 'SL'])
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name']),
            'selectedUser'   => $selectedUser,
            'currentCredits' => $currentCredits,
            'filters'        => $request->only('search', 'user_id', 'year'),
            'currentYear'    => now()->year,
        ]);
    }

    /**
     * POST /hr/leave-credits/adjust
     * Save a manual adjustment.
     */
    public function adjustStore(Request $request)
    {
        $this->authorize('hr.leave.credits.manage');

        $data = $request->validate([
            'user_id'         => 'required|exists:users,id',
            'year'            => 'required|integer|min:2000|max:2100',
            'leave_type_code' => 'required|string',
            'amount'          => 'required|numeric|not_in:0',
            'remarks'         => 'required|string|max:500',
        ]);

        try {
            $this->credits->adjustLeaveCredits(
                userId:        $data['user_id'],
                leaveTypeCode: $data['leave_type_code'],
                amount:        (float) $data['amount'],
                remarks:       $data['remarks'],
                year:          $data['year'],
                recordedBy:    Auth::id(),
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Credit adjustment saved successfully.');
    }

    // ── Service Credit Approval ───────────────────────────────────────────────

    /**
     * GET /hr/leave-credits/service-credits
     * List pending service credit records for HR to approve.
     */
    public function serviceCreditsIndex(Request $request)
    {
        $this->authorize('hr.leave.credits.service');

        $query = ServiceCreditRecord::with('user')
            ->orderByDesc('service_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('search')) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'));
        }

        return Inertia::render('HR/Leave/Credits/ServiceCredits', [
            'records' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only('status', 'search'),
        ]);
    }

    /**
     * POST /hr/leave-credits/service-credits/{record}/approve
     */
    public function serviceCreditsApprove(ServiceCreditRecord $record)
    {
        $this->authorize('hr.leave.credits.service');

        try {
            $this->credits->approveServiceCredits($record->id, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Service credit record approved.');
    }

    /**
     * POST /hr/leave-credits/service-credits/{record}/reject
     */
    public function serviceCreditsReject(Request $request, ServiceCreditRecord $record)
    {
        $this->authorize('hr.leave.credits.service');

        $request->validate(['remarks' => 'required|string|max:500']);

        if ($record->status !== 'pending') {
            return back()->with('error', "Record is not pending (current: {$record->status}).");
        }

        $record->update(['status' => 'rejected', 'remarks' => $request->remarks]);

        return back()->with('success', 'Service credit record rejected.');
    }

    // ── Transaction Ledger (per-employee) ─────────────────────────────────────

    /**
     * GET /hr/leave-credits/ledger/{user}
     * Show all leave credit transactions for a specific employee.
     */
    public function ledger(Request $request, User $user)
    {
        $this->authorize('hr.leave.credits.reports');

        $year = $request->input('year', now()->year);

        $transactions = LeaveCreditTransaction::with(['leaveType', 'recorder'])
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $balances = $this->credits->getEmployeeLeaveBalance($user->id, $year);

        return Inertia::render('HR/Leave/Credits/Ledger', [
            'employee'     => $user->only('id', 'name', 'badge_id', 'position', 'emp_category'),
            'transactions' => $transactions,
            'balances'     => $balances,
            'year'         => (int) $year,
            'years'        => range(now()->year, now()->year - 5),
        ]);
    }
}
