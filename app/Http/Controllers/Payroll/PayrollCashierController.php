<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollEmail;
use App\Models\Payroll\PayrollItem;
use App\Models\User;
use App\Services\Payroll\PayrollMatchService;
use App\Services\Payroll\PayrollParserService;
use App\Jobs\Payroll\SendPayslipJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PayrollCashierController extends Controller
{
    public function __construct(
        private PayrollParserService $parser,
        private PayrollMatchService  $matcher,
    ) {}

    // ── Batch list ────────────────────────────────────────────────────────────

    public function index()
    {
        $this->authorize('payroll.view_all');

        $batches = PayrollBatch::with('uploader:id,name')
            ->latest()
            ->paginate(20);

        return Inertia::render('Payroll/Cashier/Index', [
            'batches' => $batches,
        ]);
    }

    // ── Upload form ───────────────────────────────────────────────────────────

    public function uploadForm()
    {
        $this->authorize('payroll.upload');
        return Inertia::render('Payroll/Cashier/Upload');
    }

    // ── CSV template download ─────────────────────────────────────────────────

    public function csvTemplate(Request $request)
    {
        $this->authorize('payroll.upload');
        $types    = (array) ($request->query('type') ?: ['monthly_salary']);
        $content  = $this->parser->csvTemplate($types);
        $filename = implode('-', $types) . '-template.csv';
        return response($content, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Parse & store ─────────────────────────────────────────────────────────

    public function upload(Request $request)
    {
        $this->authorize('payroll.upload');

        $data = $request->validate([
            'main_file_base64'        => 'required|string',
            'main_filename'           => 'required|string|max:255',
            'disbursement_type'       => 'required|array|min:1',
            'disbursement_type.*'     => 'string|in:monthly_salary,hazard_pay,sala,longevity_pay,other',
            'label'                   => 'nullable|string|max:150',
            'period_start'            => 'required|date',
            'period_end'              => 'required|date|after_or_equal:period_start',
            'first_half_credit_date'  => 'nullable|date',
            'second_half_credit_date' => 'nullable|date',
            'credit_date'             => 'nullable|date',
            'payroll_no'              => 'nullable|string|max:100',
        ]);

        $disbursementTypes = $data['disbursement_type'];
        $isMonthly         = in_array('monthly_salary', $disbursementTypes);
        $periodStart       = $data['period_start'];

        // Require 1st half credit date when monthly salary is selected
        if ($isMonthly && empty($data['first_half_credit_date'])) {
            return back()->withErrors(['first_half_credit_date' => '1st Half Credit Date is required for Monthly Salary.']);
        }
        // Require a credit date when no monthly salary
        if (!$isMonthly && empty($data['credit_date'])) {
            return back()->withErrors(['credit_date' => 'Credit Date is required.']);
        }

        $label = $data['label'] ?: PayrollBatch::buildLabel($disbursementTypes);

        $overrideMeta = array_filter([
            'payroll_no'   => $data['payroll_no'] ?? null,
            'period_start' => $periodStart,
            'period_end'   => $data['period_end'],
            'month'        => (int) date('n', strtotime($periodStart)),
            'year'         => (int) date('Y', strtotime($periodStart)),
        ], fn($v) => $v !== null && $v !== '');

        try {
            $parsed = $this->parser->parseMain($data['main_file_base64'], $overrideMeta);
        } catch (\Throwable $e) {
            return back()->withErrors(['main_file_base64' => 'Could not parse the CSV file: ' . $e->getMessage()]);
        }

        if (empty($parsed['items'])) {
            return back()->withErrors(['main_file_base64' => 'No employee rows found. Check that your CSV has the correct headers and data rows.']);
        }

        try {
            $items  = $this->matcher->matchItems($parsed['items']);
            $mainKey = $this->storeBase64ToS3($data['main_file_base64'], $data['main_filename']);

            $payrollNo = $parsed['payroll_no'] ?: (
                'PR-' . $parsed['year'] . '-' . str_pad($parsed['month'], 2, '0', STR_PAD_LEFT) . '-' . substr(md5(uniqid()), 0, 6)
            );

            $batch = PayrollBatch::updateOrCreate(
                ['payroll_no' => $payrollNo],
                [
                    'batch_type'              => 'main',
                    'disbursement_type'       => $disbursementTypes,
                    'label'                   => $label,
                    'period_start'            => $parsed['period_start'],
                    'period_end'              => $parsed['period_end'],
                    'month'                   => $parsed['month'],
                    'year'                    => $parsed['year'],
                    'fund_cluster'            => $parsed['fund_cluster'],
                    'entity_name'             => $parsed['entity_name'],
                    'source_main_filename'    => $data['main_filename'],
                    'source_main_s3_key'      => $mainKey,
                    'uploaded_by'             => Auth::id(),
                    'status'                  => 'previewed',
                    'first_half_credit_date'  => $data['first_half_credit_date'] ?? null,
                    'second_half_credit_date' => $data['second_half_credit_date'] ?? null,
                    'credit_date'             => $data['credit_date'] ?? null,
                ]
            );

            foreach ($items as $itemData) {
                $this->upsertItem($batch, $itemData, $parsed['month'], $parsed['year']);
            }

            $this->recalcTotals($batch);

        } catch (\Throwable $e) {
            return back()->withErrors(['main_file_base64' => '[DB/S3] ' . $e->getMessage()]);
        }

        return redirect()->route('payroll.cashier.preview', $batch->id)
            ->with('success', 'Payroll parsed successfully.');
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function preview(PayrollBatch $batch)
    {
        $this->authorize('payroll.view_all');

        $items = PayrollItem::with('employee:id,name,email')
            ->where('batch_id', $batch->id)
            ->orWhere(function ($q) use ($batch) {
                $q->where('month', $batch->month)->where('year', $batch->year);
            })
            ->get()
            ->groupBy('match_status');

        $users = User::where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_no', 'position']);

        return Inertia::render('Payroll/Cashier/Preview', [
            'batch'     => $batch,
            'matched'   => $items->get('matched', collect())->values(),
            'probable'  => $items->get('probable', collect())->values(),
            'unmatched' => $items->get('unmatched', collect())->values(),
            'users'     => $users,
        ]);
    }

    // ── Resolve unmatched ─────────────────────────────────────────────────────

    public function resolve(Request $request, PayrollBatch $batch)
    {
        $this->authorize('payroll.upload');

        $data = $request->validate([
            'resolutions'              => 'required|array',
            'resolutions.*.item_id'    => 'required|integer|exists:payroll_items,id',
            'resolutions.*.user_id'    => 'required|integer|exists:users,id',
            'resolutions.*.save_alias' => 'boolean',
        ]);

        foreach ($data['resolutions'] as $res) {
            $item = PayrollItem::findOrFail($res['item_id']);
            abort_if($item->month !== $batch->month || $item->year !== $batch->year, 403);

            $item->update([
                'matched_user_id' => $res['user_id'],
                'match_status'    => 'manual',
            ]);

            if (!empty($res['save_alias'])) {
                $this->matcher->saveAlias($item->employee_name_raw, $res['user_id'], Auth::id());
            }
        }

        return back()->with('success', 'Matches saved.');
    }

    // ── Manual adjustments ────────────────────────────────────────────────────

    public function adjustments(Request $request, PayrollBatch $batch)
    {
        $this->authorize('payroll.upload');

        $data = $request->validate([
            'adjustments'           => 'required|array',
            'adjustments.*.item_id' => 'required|integer|exists:payroll_items,id',
            'adjustments.*.fields'  => 'required|array',
        ]);

        foreach ($data['adjustments'] as $adj) {
            $item = PayrollItem::findOrFail($adj['item_id']);
            $item->update(['adjustments_json' => $adj['fields']]);
        }

        return back()->with('success', 'Adjustments saved.');
    }

    // ── Send (1st half / single disbursement) ────────────────────────────────

    public function send(PayrollBatch $batch)
    {
        $this->authorize('payroll.send');

        $items = PayrollItem::where('batch_id', $batch->id)
            ->whereNotNull('matched_user_id')
            ->whereIn('match_status', ['matched', 'probable', 'manual'])
            ->with('employee:id,name,email,status')
            ->get();

        $delay = 0;

        foreach ($items as $item) {
            $emp = $item->employee;
            if (!$emp || $emp->status === 'inactive' || !$emp->email) continue;

            if ($batch->isMonthly()) {
                // Always send 1st half notification
                $emailRecord = PayrollEmail::create([
                    'payroll_item_id' => $item->id,
                    'send_type'       => 'first_half',
                    'to_email'        => $emp->email,
                    'bcc_email'       => config('mail.payroll_bcc'),
                    'subject'         => $this->emailSubject($batch, 'first_half'),
                    'status'          => 'queued',
                ]);
                SendPayslipJob::dispatch($emailRecord->id)->delay(now()->addSeconds($delay++ * 2));

                // Send 2nd half notification immediately only if date is already set
                if ($batch->second_half_credit_date) {
                    $emailRecord = PayrollEmail::create([
                        'payroll_item_id' => $item->id,
                        'send_type'       => 'second_half',
                        'to_email'        => $emp->email,
                        'bcc_email'       => config('mail.payroll_bcc'),
                        'subject'         => $this->emailSubject($batch, 'second_half'),
                        'status'          => 'queued',
                    ]);
                    SendPayslipJob::dispatch($emailRecord->id)->delay(now()->addSeconds($delay++ * 2));
                }
            } else {
                // Single non-monthly disbursement — one email per employee
                $sendType = (array) $batch->disbursement_type;
                $emailRecord = PayrollEmail::create([
                    'payroll_item_id' => $item->id,
                    'send_type'       => implode('+', $sendType),
                    'to_email'        => $emp->email,
                    'bcc_email'       => config('mail.payroll_bcc'),
                    'subject'         => $this->emailSubject($batch),
                    'status'          => 'queued',
                ]);
                SendPayslipJob::dispatch($emailRecord->id)->delay(now()->addSeconds($delay++ * 2));
            }
        }

        $batch->update(['status' => 'sending']);

        return back()->with('success', 'Payslips queued for sending.');
    }

    // ── Send 2nd half (after date is known) ──────────────────────────────────

    public function sendSecondHalf(Request $request, PayrollBatch $batch)
    {
        $this->authorize('payroll.send');

        abort_unless($batch->isMonthly(), 422, 'Not a monthly salary batch.');
        abort_if($batch->second_half_credit_date, 422, '2nd half notifications have already been sent for this batch.');

        $data = $request->validate([
            'second_half_credit_date' => 'required|date',
        ]);

        $batch->update(['second_half_credit_date' => $data['second_half_credit_date']]);

        $items = PayrollItem::where('batch_id', $batch->id)
            ->whereNotNull('matched_user_id')
            ->whereIn('match_status', ['matched', 'probable', 'manual'])
            ->with('employee:id,name,email,status')
            ->get();

        $delay = 0;
        foreach ($items as $item) {
            $emp = $item->employee;
            if (!$emp || $emp->status === 'inactive' || !$emp->email) continue;

            $emailRecord = PayrollEmail::create([
                'payroll_item_id' => $item->id,
                'send_type'       => 'second_half',
                'to_email'        => $emp->email,
                'bcc_email'       => config('mail.payroll_bcc'),
                'subject'         => $this->emailSubject($batch, 'second_half'),
                'status'          => 'queued',
            ]);
            SendPayslipJob::dispatch($emailRecord->id)->delay(now()->addSeconds($delay++ * 2));
        }

        return back()->with('success', '2nd half notifications queued for sending.');
    }

    // ── Send status (polling) ─────────────────────────────────────────────────

    public function status(PayrollBatch $batch)
    {
        $this->authorize('payroll.view_all');

        $items = PayrollItem::where('batch_id', $batch->id)
            ->whereNotNull('matched_user_id')
            ->with(['employee:id,name,email', 'emails' => fn($q) => $q->latest()->limit(1)])
            ->get();

        $counts = ['total' => $items->count(), 'queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];

        $rows = $items->map(function ($item) use (&$counts) {
            $latest = $item->emails->first();
            $status = $latest?->status ?? 'unsent';
            if (isset($counts[$status])) $counts[$status]++;
            return [
                'id'           => $item->id,
                'name'         => $item->employee?->name ?? $item->employee_name_raw,
                'email'        => $item->employee?->email,
                'email_status' => $status,
                'sent_at'      => $latest?->sent_at,
                'last_error'   => $latest?->last_error,
            ];
        });

        if ($counts['sent'] === $counts['total'] && $counts['total'] > 0) {
            $batch->update(['status' => 'completed']);
        }

        return response()->json(['counts' => $counts, 'rows' => $rows]);
    }

    // ── Resend ────────────────────────────────────────────────────────────────

    public function resend(Request $request, PayrollBatch $batch)
    {
        $this->authorize('payroll.send');

        $data = $request->validate([
            'item_ids'   => 'required|array',
            'item_ids.*' => 'integer|exists:payroll_items,id',
        ]);

        foreach ($data['item_ids'] as $itemId) {
            $item = PayrollItem::with('employee')->findOrFail($itemId);
            $emp  = $item->employee;
            if (!$emp || !$emp->email) continue;

            $emailRecord = PayrollEmail::create([
                'payroll_item_id' => $item->id,
                'send_type'       => 'resend',
                'to_email'        => $emp->email,
                'bcc_email'       => config('mail.payroll_bcc'),
                'subject'         => $this->emailSubject($batch),
                'status'          => 'queued',
            ]);

            SendPayslipJob::dispatch($emailRecord->id);
        }

        return back()->with('success', 'Resend queued.');
    }

    // ── Audit CSV ─────────────────────────────────────────────────────────────

    public function auditCsv(PayrollBatch $batch)
    {
        $this->authorize('payroll.view_all');

        $emails = PayrollEmail::with('item.employee:id,name,email')
            ->whereHas('item', fn($q) => $q->where('batch_id', $batch->id))
            ->orderBy('created_at')
            ->get();

        $csv = "Item ID,Employee,Email,Send Type,Status,Attempts,Sent At,Error\n";
        foreach ($emails as $e) {
            $csv .= implode(',', [
                $e->payroll_item_id,
                '"' . ($e->item->employee?->name ?? '') . '"',
                $e->to_email,
                $e->send_type,
                $e->status,
                $e->attempts,
                $e->sent_at?->toDateTimeString() ?? '',
                '"' . str_replace('"', '""', $e->last_error ?? '') . '"',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"payroll-{$batch->payroll_no}-audit.csv\"",
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function upsertItem(PayrollBatch $batch, array $data, int $month, int $year): void
    {
        PayrollItem::updateOrCreate(
            [
                'year'            => $year,
                'month'           => $month,
                'batch_id'        => $batch->id,
                'matched_user_id' => $data['matched_user_id'] ?? null,
                ...($data['matched_user_id'] ? [] : ['employee_name_raw' => $data['employee_name_raw']]),
            ],
            array_merge($data, [
                'batch_id' => $batch->id,
                'month'    => $month,
                'year'     => $year,
            ])
        );
    }

    private function recalcTotals(PayrollBatch $batch): void
    {
        $totals = PayrollItem::where('batch_id', $batch->id)
            ->selectRaw('SUM(gross_earnings) as g, SUM(total_deductions) as d, SUM(net_pay) as n')
            ->first();

        $batch->update([
            'totals_gross'      => $totals->g ?? 0,
            'totals_deductions' => $totals->d ?? 0,
            'totals_net'        => $totals->n ?? 0,
        ]);
    }

    private function storeBase64ToS3(string $base64, string $filename): string
    {
        $data = $base64;
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $key = 'payroll/uploads/' . date('Y/m') . '/' . uniqid() . '_' . basename($filename);
        Storage::disk('s3')->put($key, base64_decode($data));
        return $key;
    }

    private function emailSubject(PayrollBatch $batch, string $sendType = null): string
    {
        $period = \Carbon\Carbon::parse($batch->period_start)->format('M Y');
        $label  = $batch->disbursementLabel($sendType);
        return "{$label} — {$period} — PSHS-CRC";
    }
}
