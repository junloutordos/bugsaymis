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
        $type     = $request->query('type', 'monthly_salary');
        $content  = $this->parser->csvTemplate($type);
        $filename = str_replace('_', '-', $type) . '-template.csv';
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
            'disbursement_type'       => 'required|string|in:monthly_salary,hazard_pay,sala,longevity_pay,other',
            'label'                   => 'nullable|string|max:150',
            'period_start'            => 'required|date',
            'period_end'              => 'required|date|after_or_equal:period_start',
            'first_half_credit_date'  => 'required_if:disbursement_type,monthly_salary|nullable|date',
            'second_half_credit_date' => 'required_if:disbursement_type,monthly_salary|nullable|date',
            'credit_date'             => 'required_unless:disbursement_type,monthly_salary|nullable|date',
            'payroll_no'              => 'nullable|string|max:100',
        ]);

        $disbursementType = $data['disbursement_type'];
        $periodStart      = $data['period_start'];

        $label = $data['label'] ?: match($disbursementType) {
            'monthly_salary' => 'Monthly Salary',
            'hazard_pay'     => 'Hazard Pay',
            'sala'           => 'Subsistence Allowance',
            'longevity_pay'  => 'Longevity Pay',
            default          => 'Other Allowance',
        };

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
                    'disbursement_type'       => $disbursementType,
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

    // ── Send ──────────────────────────────────────────────────────────────────

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

            if ($batch->disbursement_type === 'monthly_salary') {
                // Two notifications: one for each half
                foreach (['first_half', 'second_half'] as $sendType) {
                    $emailRecord = PayrollEmail::create([
                        'payroll_item_id' => $item->id,
                        'send_type'       => $sendType,
                        'to_email'        => $emp->email,
                        'bcc_email'       => config('mail.payroll_bcc'),
                        'subject'         => $this->emailSubject($batch, $sendType),
                        'status'          => 'queued',
                    ]);
                    SendPayslipJob::dispatch($emailRecord->id)->delay(now()->addSeconds($delay++ * 2));
                }
            } else {
                $emailRecord = PayrollEmail::create([
                    'payroll_item_id' => $item->id,
                    'send_type'       => $batch->disbursement_type,
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
        $type   = $sendType ?? $batch->disbursement_type;

        return match($type) {
            'first_half'    => "1st Half Salary — {$period} — PSHS-CRC",
            'second_half'   => "2nd Half Salary — {$period} — PSHS-CRC",
            'hazard_pay'    => "Hazard Pay — {$period} — PSHS-CRC",
            'sala'          => "Subsistence Allowance — {$period} — PSHS-CRC",
            'longevity_pay' => "Longevity Pay — {$period} — PSHS-CRC",
            default         => ($batch->label ?: 'Allowance') . " — {$period} — PSHS-CRC",
        };
    }
}
