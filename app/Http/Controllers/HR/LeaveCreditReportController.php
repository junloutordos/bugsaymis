<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveCredit;
use App\Models\HR\LeaveCreditTransaction;
use App\Models\HR\LeaveType;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeaveCreditReportController extends Controller
{
    // ── 1. Leave Credit Ledger ────────────────────────────────────────────────

    /**
     * GET /hr/reports/leave-credits/ledger
     * Full transaction audit trail, filterable by employee / leave type / tx type / date range.
     * Also accessible per-employee via /hr/leave-credits/ledger/{user} (Phase 4 route).
     */
    public function ledger(Request $request)
    {
        $this->authorize('hr.leave.credits.reports');

        $year         = (int) $request->input('year', now()->year);
        $userId       = $request->input('user_id');
        $leaveTypeId  = $request->input('leave_type_id');
        $txType       = $request->input('type');
        $dateFrom     = $request->input('date_from');
        $dateTo       = $request->input('date_to');

        $query = LeaveCreditTransaction::with(['user', 'leaveType', 'recorder'])
            ->where('year', $year)
            ->orderByDesc('created_at');

        if ($userId)      $query->where('user_id', $userId);
        if ($leaveTypeId) $query->where('leave_type_id', $leaveTypeId);
        if ($txType)      $query->where('type', $txType);
        if ($dateFrom)    $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)      $query->whereDate('created_at', '<=', $dateTo);

        $transactions = $query->paginate(30)->withQueryString();

        // Summary totals per type for the filtered set (unfiltered pagination count)
        $summary = LeaveCreditTransaction::where('year', $year)
            ->when($userId,      fn ($q) => $q->where('user_id', $userId))
            ->when($leaveTypeId, fn ($q) => $q->where('leave_type_id', $leaveTypeId))
            ->when($txType,      fn ($q) => $q->where('type', $txType))
            ->selectRaw('type, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('type')
            ->get();

        // Per-employee balance summary (earned, used, balance) for the filtered
        // employee/leave-type/year — lets HR see current standing at a glance
        // alongside the raw transaction trail, with a link into the full
        // per-employee ledger (hr.leave-credits.ledger).
        $balanceSummary = LeaveCredit::with(['user', 'leaveType'])
            ->where('year', $year)
            ->whereHas('user', fn ($q) => $q->whereNotNull('emp_category'))
            ->when($userId,      fn ($q) => $q->where('user_id', $userId))
            ->when($leaveTypeId, fn ($q) => $q->where('leave_type_id', $leaveTypeId))
            ->orderBy(
                User::select('name')->whereColumn('users.id', 'leave_credits.user_id')->limit(1)
            )
            ->get();

        return Inertia::render('HR/Leave/Reports/Ledger', [
            'transactions'   => $transactions,
            'summary'        => $summary,
            'balanceSummary' => $balanceSummary,
            'leaveTypes'     => LeaveType::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
            'employees'      => User::whereNotNull('emp_category')
                ->orderBy('name')
                ->get(['id', 'name', 'badge_id']),
            'txTypes'        => ['INITIAL','ACCRUAL','DEDUCTION','ADJUSTMENT','RESTORATION','MONETIZATION','CARRYOVER','FORFEITURE'],
            'filters'        => $request->only('year', 'user_id', 'leave_type_id', 'type', 'date_from', 'date_to'),
            'currentYear'    => now()->year,
            'years'          => range(now()->year, max(now()->year - 5, 2024)),
        ]);
    }

    // ── 2. Monthly Accrual Report ─────────────────────────────────────────────

    /**
     * GET /hr/reports/leave-credits/accrual
     * Shows which employees received accruals per month, grouped by month.
     */
    public function accrual(Request $request)
    {
        $this->authorize('hr.leave.credits.reports');

        $year  = (int) $request->input('year', now()->year);
        $month = $request->input('month'); // null = all months

        $query = LeaveCreditTransaction::with(['user', 'leaveType'])
            ->where('year', $year)
            ->where('type', 'ACCRUAL')
            ->orderBy('created_at');

        if ($month) $query->whereMonth('created_at', $month);

        // Per-month summary: count of employees accrued + total days
        $monthlySummary = LeaveCreditTransaction::where('year', $year)
            ->where('type', 'ACCRUAL')
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->selectRaw('MONTH(created_at) as month, leave_type_id, COUNT(DISTINCT user_id) as employees, SUM(amount) as total_days')
            ->groupBy('month', 'leave_type_id')
            ->with('leaveType')
            ->get();

        // Build month → {VL: {employees, total}, SL: {...}} map
        $monthMap = [];
        foreach ($monthlySummary as $row) {
            $m    = str_pad($row->month, 2, '0', STR_PAD_LEFT);
            $code = $row->leaveType?->code ?? '?';
            $monthMap[$m][$code] = [
                'employees'  => $row->employees,
                'total_days' => round((float) $row->total_days, 4),
            ];
        }

        $rows = $query->paginate(50)->withQueryString();

        return Inertia::render('HR/Leave/Reports/Accrual', [
            'rows'         => $rows,
            'monthMap'     => $monthMap,
            'year'         => $year,
            'month'        => $month,
            'years'        => range(now()->year, max(now()->year - 5, 2024)),
            'months'       => [
                '01' => 'January',  '02' => 'February', '03' => 'March',
                '04' => 'April',    '05' => 'May',       '06' => 'June',
                '07' => 'July',     '08' => 'August',    '09' => 'September',
                '10' => 'October',  '11' => 'November',  '12' => 'December',
            ],
            'filters'      => $request->only('year', 'month'),
        ]);
    }

    // ── 3. Leave Utilization Report ───────────────────────────────────────────

    /**
     * GET /hr/reports/leave-credits/utilization
     * Per-employee summary: earned, used, balance, utilization rate.
     * Filterable by year, emp_category, leave type.
     */
    public function utilization(Request $request)
    {
        $this->authorize('hr.leave.credits.reports');

        $year        = (int) $request->input('year', now()->year);
        $category    = $request->input('emp_category');
        $leaveTypeId = $request->input('leave_type_id');

        $query = LeaveCredit::with(['user', 'leaveType'])
            ->where('year', $year)
            ->whereHas('user', fn ($q) => $q->whereNotNull('emp_category'))
            ->orderBy(
                User::select('name')->whereColumn('users.id', 'leave_credits.user_id')->limit(1)
            );

        if ($leaveTypeId) $query->where('leave_type_id', $leaveTypeId);

        if ($category) {
            $query->whereHas('user', fn ($q) => $q->where('emp_category', $category));
        }

        $credits = $query->paginate(40)->withQueryString();

        // Overall aggregates (unfiltered by pagination)
        $aggregates = LeaveCredit::where('year', $year)
            ->when($leaveTypeId, fn ($q) => $q->where('leave_type_id', $leaveTypeId))
            ->when($category, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('emp_category', $category)))
            ->selectRaw('
                leave_type_id,
                SUM(earned + carried_over) as total_available,
                SUM(used)                  as total_used,
                SUM(forfeited)             as total_forfeited,
                SUM(monetized)             as total_monetized,
                SUM(balance)               as total_balance,
                COUNT(*)                   as employee_count
            ')
            ->groupBy('leave_type_id')
            ->with('leaveType')
            ->get();

        return Inertia::render('HR/Leave/Reports/Utilization', [
            'credits'    => $credits,
            'aggregates' => $aggregates,
            'leaveTypes' => LeaveType::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name']),
            'categories' => ['Plantilla Teaching', 'Plantilla Non-Teaching', 'COS Teaching', 'COS Non Teaching'],
            'filters'    => $request->only('year', 'emp_category', 'leave_type_id'),
            'year'       => $year,
            'years'      => range(now()->year, max(now()->year - 5, 2024)),
        ]);
    }
}
