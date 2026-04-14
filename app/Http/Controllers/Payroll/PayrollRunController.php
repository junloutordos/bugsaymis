<?php

namespace App\Http\Controllers\Payroll;

use App\Enums\ApprovalStep;
use App\Http\Controllers\Controller;
use App\Jobs\Payroll\ProcessPayrollRun;
use App\Models\Payroll\PayrollRecord;
use App\Models\Payroll\PayrollRun;
use App\Services\SnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PayrollRunController extends Controller
{
    public function __construct(private SnapshotService $snapshots) {}
    public function index()
    {
        $this->authorize('payroll.view');

        $runs = PayrollRun::withCount('payrollRecords')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('period')
            ->paginate(20);

        return Inertia::render('Payroll/Index', [
            'runs' => $runs,
        ]);
    }

    public function create()
    {
        $this->authorize('payroll.manage');

        // Suggest next period
        $last = PayrollRun::orderByDesc('year')->orderByDesc('month')->orderByDesc('period')->first();

        $suggestion = $this->nextPeriodSuggestion($last);

        return Inertia::render('Payroll/Create', [
            'suggestion' => $suggestion,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('payroll.manage');

        $data = $request->validate([
            'year'    => 'required|integer|min:2020|max:2099',
            'month'   => 'required|integer|min:1|max:12',
            'period'  => 'required|in:1st,2nd',
            'pay_date' => 'nullable|date',
        ]);

        // Prevent duplicate
        if (PayrollRun::where('year', $data['year'])
                      ->where('month', $data['month'])
                      ->where('period', $data['period'])
                      ->exists()) {
            return back()->withErrors(['period' => 'A payroll run already exists for this period.']);
        }

        [$periodStart, $periodEnd] = $this->periodDates($data['year'], $data['month'], $data['period']);

        $run = PayrollRun::create([
            'year'         => $data['year'],
            'month'        => $data['month'],
            'period'       => $data['period'],
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'pay_date'     => $data['pay_date'] ?? null,
            'status'       => 'draft',
            'prepared_by'  => Auth::id(),
        ]);

        $this->snapshots->recordApproval(
            approvable: $run,
            step:       ApprovalStep::PAYROLL_PREPARED,
            sequence:   1,
            action:     'prepared',
            approver:   Auth::user(),
        );

        return redirect()->route('payroll.show', $run)->with('success', "Payroll run {$run->control_no} created.");
    }

    public function show(PayrollRun $payrollRun)
    {
        $this->authorize('payroll.view');

        $records = PayrollRecord::where('payroll_run_id', $payrollRun->id)
            ->with(['user', 'allowances.allowanceType', 'deductions.allowanceType'])
            ->orderBy('user_id')
            ->paginate(30);

        return Inertia::render('Payroll/Show', [
            'run'     => $payrollRun->load('preparedBy', 'approvedBy'),
            'records' => $records,
        ]);
    }

    public function process(PayrollRun $payrollRun)
    {
        $this->authorize('payroll.process');

        if (! in_array($payrollRun->status, ['draft', 'for_review'])) {
            return back()->with('error', 'Only draft or for-review runs can be re-processed.');
        }

        ProcessPayrollRun::dispatch($payrollRun->id);

        return back()->with('success', "Processing started for {$payrollRun->control_no}. Refresh in a moment.");
    }

    public function approve(Request $request, PayrollRun $payrollRun)
    {
        $this->authorize('payroll.approve');

        if ($payrollRun->status !== 'for_review') {
            return back()->with('error', 'Only runs with status "for review" can be approved.');
        }

        $data = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $payrollRun->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'remarks'     => $data['remarks'] ?? null,
        ]);

        $this->snapshots->recordApproval(
            approvable: $payrollRun,
            step:       ApprovalStep::PAYROLL_APPROVED,
            sequence:   2,
            action:     'approved',
            approver:   Auth::user(),
            remarks:    $data['remarks'] ?? '',
        );

        // Mark all records as approved
        PayrollRecord::where('payroll_run_id', $payrollRun->id)->update(['status' => 'approved']);

        return back()->with('success', "Payroll run {$payrollRun->control_no} approved.");
    }

    public function release(PayrollRun $payrollRun)
    {
        $this->authorize('payroll.approve');

        if ($payrollRun->status !== 'approved') {
            return back()->with('error', 'Only approved runs can be released.');
        }

        $payrollRun->update(['status' => 'released']);

        PayrollRecord::where('payroll_run_id', $payrollRun->id)->update(['status' => 'released']);

        // Mark DTR records as posted
        \App\Models\HR\DtrRecord::where('work_date', '>=', $payrollRun->period_start)
            ->where('work_date', '<=', $payrollRun->period_end)
            ->whereIn('user_id', PayrollRecord::where('payroll_run_id', $payrollRun->id)->pluck('user_id'))
            ->update(['is_posted' => true]);

        return back()->with('success', "Payroll run {$payrollRun->control_no} released. DTR records locked.");
    }

    public function cancel(PayrollRun $payrollRun)
    {
        $this->authorize('payroll.manage');

        if ($payrollRun->status === 'released') {
            return back()->with('error', 'Released payroll runs cannot be cancelled.');
        }

        $payrollRun->update(['status' => 'cancelled']);

        return back()->with('success', "Payroll run {$payrollRun->control_no} cancelled.");
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function periodDates(int $year, int $month, string $period): array
    {
        if ($period === '1st') {
            return [
                Carbon::createFromDate($year, $month, 1)->toDateString(),
                Carbon::createFromDate($year, $month, 15)->toDateString(),
            ];
        }

        $lastDay = Carbon::createFromDate($year, $month, 1)->endOfMonth()->day;
        return [
            Carbon::createFromDate($year, $month, 16)->toDateString(),
            Carbon::createFromDate($year, $month, $lastDay)->toDateString(),
        ];
    }

    private function nextPeriodSuggestion(?PayrollRun $last): array
    {
        if (! $last) {
            return ['year' => now()->year, 'month' => now()->month, 'period' => '1st'];
        }

        if ($last->period === '1st') {
            return ['year' => $last->year, 'month' => $last->month, 'period' => '2nd'];
        }

        $next = Carbon::createFromDate($last->year, $last->month, 1)->addMonth();
        return ['year' => $next->year, 'month' => $next->month, 'period' => '1st'];
    }
}
