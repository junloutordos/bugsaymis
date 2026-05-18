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
use Illuminate\Support\Facades\DB;
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

        // Group batches by period so uploads for the same month appear as one entry
        $periods = PayrollBatch::with('uploader:id,name')
            ->orderByDesc('year')->orderByDesc('month')->orderByDesc('id')
            ->get()
            ->groupBy(fn($b) => $b->year . '-' . str_pad($b->month, 2, '0', STR_PAD_LEFT))
            ->map(fn($batches) => [
                'year'         => $batches->first()->year,
                'month'        => $batches->first()->month,
                'period_start' => $batches->first()->period_start,
                'period_end'   => $batches->first()->period_end,
                'batches'      => $batches->values(),
            ])
            ->values();

        return Inertia::render('Payroll/Cashier/Index', ['periods' => $periods]);
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
            'period_start'                        => 'required|date',
            'period_end'                          => 'required|date|after_or_equal:period_start',
            'types'                               => 'required|array|min:1',
            'types.*.type'                        => 'required|string|in:monthly_salary,hazard_pay,sala,longevity_pay,year_end_bonus,clothing_allowance,midyear_bonus,cna,other',
            'types.*.label'                       => 'nullable|string|max:150',
            'types.*.base64'                      => 'required|string',
            'types.*.filename'                    => 'required|string|max:255',
            'types.*.first_half_credit_date'      => 'nullable|date',
            'types.*.second_half_credit_date'     => 'nullable|date',
            'types.*.credit_date'                 => 'nullable|date',
            'types.*.payroll_no'                  => 'nullable|string|max:100',
        ]);

        $periodStart = $data['period_start'];
        $periodEnd   = $data['period_end'];
        $month       = (int) date('n', strtotime($periodStart));
        $year        = (int) date('Y', strtotime($periodStart));

        // Per-type validation
        foreach ($data['types'] as $i => $entry) {
            $isMonthly = $entry['type'] === 'monthly_salary';
            if ($isMonthly && empty($entry['first_half_credit_date'])) {
                return back()->withErrors(["types.{$i}.first_half_credit_date" => '1st Half Credit Date is required for Monthly Salary.']);
            }
            if (!$isMonthly && empty($entry['credit_date'])) {
                return back()->withErrors(["types.{$i}.credit_date" => "ATM Credit Date is required for {$entry['type']}."]);
            }
        }

        $createdBatches = [];

        foreach ($data['types'] as $i => $entry) {
            $isMonthly = $entry['type'] === 'monthly_salary';
            $label     = $entry['label'] ?: PayrollBatch::buildLabel([$entry['type']]);

            $overrideMeta = array_filter([
                'payroll_no'   => $entry['payroll_no'] ?? null,
                'period_start' => $periodStart,
                'period_end'   => $periodEnd,
                'month'        => $month,
                'year'         => $year,
            ], fn($v) => $v !== null && $v !== '');

            try {
                $parsed = $this->parser->parseMain($entry['base64'], $overrideMeta);
            } catch (\Throwable $e) {
                return back()->withErrors(["types.{$i}.base64" => "[{$label}] Could not parse CSV: " . $e->getMessage()]);
            }

            if (empty($parsed['items'])) {
                return back()->withErrors(["types.{$i}.base64" => "[{$label}] No employee rows found. Check headers and data rows."]);
            }

            try {
                $items     = $this->matcher->matchItems($parsed['items']);
                $s3Key     = $this->storeBase64ToS3($entry['base64'], $entry['filename']);

                $payrollNo = $parsed['payroll_no'] ?: (
                    'PR-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . strtoupper($entry['type'][0]) . '-' . substr(md5(uniqid()), 0, 5)
                );

                $batch = PayrollBatch::updateOrCreate(
                    ['payroll_no' => $payrollNo],
                    [
                        'batch_type'              => 'main',
                        'disbursement_type'       => [$entry['type']],
                        'label'                   => $label,
                        'period_start'            => $parsed['period_start'],
                        'period_end'              => $parsed['period_end'],
                        'month'                   => $parsed['month'],
                        'year'                    => $parsed['year'],
                        'fund_cluster'            => $parsed['fund_cluster'],
                        'entity_name'             => $parsed['entity_name'],
                        'source_main_filename'    => $entry['filename'],
                        'source_main_s3_key'      => $s3Key,
                        'uploaded_by'             => Auth::id(),
                        'status'                  => 'previewed',
                        'first_half_credit_date'  => $isMonthly ? ($entry['first_half_credit_date'] ?? null) : null,
                        'second_half_credit_date' => $isMonthly ? ($entry['second_half_credit_date'] ?? null) : null,
                        'credit_date'             => !$isMonthly ? ($entry['credit_date'] ?? null) : null,
                    ]
                );

                foreach ($items as $itemData) {
                    $this->upsertItem($batch, $itemData, $parsed['month'], $parsed['year']);
                }

                $this->recalcTotals($batch, $parsed['items']);
                $createdBatches[] = $batch;

            } catch (\Throwable $e) {
                return back()->withErrors(["types.{$i}.base64" => "[{$label}] " . $e->getMessage()]);
            }
        }

        // Always go to first batch preview; flash the others so the user knows to send them too
        $first  = array_shift($createdBatches);
        $others = array_map(fn($b) => ['id' => $b->id, 'label' => $b->label, 'payroll_no' => $b->payroll_no], $createdBatches);

        return redirect()->route('payroll.cashier.preview', $first->id)
            ->with('success', 'Payroll parsed successfully.')
            ->with('also_uploaded', $others);
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    public function preview(PayrollBatch $batch)
    {
        $this->authorize('payroll.view_all');

        $items = PayrollItem::with('employee:id,name,email')
            ->whereHas('batches', fn($q) => $q->where('payroll_batches.id', $batch->id))
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
            abort_if(
                !DB::table('payroll_batch_items')->where('batch_id', $batch->id)->where('item_id', $item->id)->exists(),
                403
            );

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

        $items = PayrollItem::whereHas('batches', fn($q) => $q->where('payroll_batches.id', $batch->id))
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

        $items = PayrollItem::whereHas('batches', fn($q) => $q->where('payroll_batches.id', $batch->id))
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

        $items = PayrollItem::whereHas('batches', fn($q) => $q->where('payroll_batches.id', $batch->id))
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
            ->whereHas('item.batches', fn($q) => $q->where('payroll_batches.id', $batch->id))
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
        $userId = $data['matched_user_id'] ?? null;

        // One item per batch per matched employee (unique: batch_id + matched_user_id)
        $existing = $userId
            ? PayrollItem::where('batch_id', $batch->id)
                ->where('matched_user_id', $userId)
                ->first()
            : null;

        if ($existing) {
            $existing->update(array_merge($data, ['month' => $month, 'year' => $year]));
            $itemId = $existing->id;
        } else {
            $item   = PayrollItem::create(array_merge($data, [
                'batch_id' => $batch->id,
                'month'    => $month,
                'year'     => $year,
            ]));
            $itemId = $item->id;
        }

        DB::table('payroll_batch_items')->insertOrIgnore([
            'batch_id' => $batch->id,
            'item_id'  => $itemId,
        ]);
    }

    private function recalcTotals(PayrollBatch $batch, array $parsedItems): void
    {
        // Totals are computed from the raw CSV data so each batch reflects its own contribution
        $batch->update([
            'totals_gross'      => round(array_sum(array_column($parsedItems, 'gross_earnings')), 2),
            'totals_deductions' => round(array_sum(array_column($parsedItems, 'total_deductions')), 2),
            'totals_net'        => round(array_sum(array_column($parsedItems, 'net_pay')), 2),
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
