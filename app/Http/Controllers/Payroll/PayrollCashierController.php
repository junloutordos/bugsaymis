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
use Illuminate\Support\Str;
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

        try { return $this->processUpload($request); }
        catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (\Throwable $e) {
            \Log::error('Payroll upload error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withErrors(['types' => $e->getMessage()]);
        }
    }

    private function processUpload(Request $request)
    {
        $data = $request->validate([
            'period_start'                         => 'nullable|date',
            'period_end'                           => 'nullable|date|after_or_equal:period_start',
            'types'                                => 'required|array|min:1',
            'types.*.type'                         => 'required|string|in:monthly_salary,hazard_pay,sala,longevity_pay,year_end_bonus,clothing_allowance,midyear_bonus,cna,other,cash_advance,reimbursement',
            'types.*.label'                        => 'nullable|string|max:150',
            'types.*.payroll_no'                   => 'nullable|string|max:100',
            // Monthly-only (at type level)
            'types.*.base64'                       => 'nullable|string',
            'types.*.filename'                     => 'nullable|string|max:255',
            'types.*.first_half_credit_date'       => 'nullable|date',
            'types.*.second_half_credit_date'      => 'nullable|date',
            // Non-monthly: credit_date shared across all entries in this type
            'types.*.credit_date'                  => 'nullable|date',
            'types.*.purpose'                      => 'nullable|string|max:500',
            // Non-monthly entries (one per month)
            'types.*.entries'                      => 'nullable|array|min:1',
            'types.*.entries.*.period_month'       => 'nullable|integer|min:1|max:12',
            'types.*.entries.*.period_year'        => 'nullable|integer|min:2000|max:2100',
            'types.*.entries.*.base64'             => 'nullable|string',
            'types.*.entries.*.filename'           => 'nullable|string|max:255',
        ]);

        // Manual validation
        $hasMonthly = collect($data['types'])->contains('type', 'monthly_salary');
        if ($hasMonthly && (empty($data['period_start']) || empty($data['period_end']))) {
            return back()->withErrors(['period_start' => 'Period Start and End are required for Monthly Salary.']);
        }

        foreach ($data['types'] as $i => $entry) {
            $isMonthly = $entry['type'] === 'monthly_salary';
            if ($isMonthly) {
                if (empty($entry['base64'])) {
                    return back()->withErrors(["types.{$i}.base64" => 'CSV file is required for Monthly Salary.']);
                }
                if (empty($entry['first_half_credit_date'])) {
                    return back()->withErrors(["types.{$i}.first_half_credit_date" => '1st Half Credit Date is required for Monthly Salary.']);
                }
            } else {
                $entries = $entry['entries'] ?? [];
                if (empty($entries)) {
                    return back()->withErrors(["types.{$i}.entries" => 'At least one CSV file entry is required.']);
                }
                if (empty($entry['credit_date'])) {
                    return back()->withErrors(["types.{$i}.credit_date" => "ATM Credit Date is required for {$entry['type']}."]);
                }
                if (in_array($entry['type'], ['cash_advance', 'reimbursement']) && empty($entry['purpose'])) {
                    return back()->withErrors(["types.{$i}.purpose" => 'Purpose is required for Cash Advance and Reimbursement.']);
                }
                foreach ($entries as $ei => $fileEntry) {
                    if (empty($fileEntry['base64'])) {
                        return back()->withErrors(["types.{$i}.entries.{$ei}.base64" => 'CSV file is required.']);
                    }
                    if (empty($fileEntry['period_month']) || empty($fileEntry['period_year'])) {
                        return back()->withErrors(["types.{$i}.entries.{$ei}.period_month" => 'Month and year are required.']);
                    }
                }
            }
        }

        $primaryBatches = [];

        foreach ($data['types'] as $i => $entry) {
            $isMonthly = $entry['type'] === 'monthly_salary';
            $label     = $entry['label'] ?: PayrollBatch::buildLabel([$entry['type']]);

            if ($isMonthly) {
                $periodStart  = $data['period_start'];
                $periodEnd    = $data['period_end'];
                $month        = (int) date('n', strtotime($periodStart));
                $year         = (int) date('Y', strtotime($periodStart));

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
                    $payrollNo = $parsed['payroll_no'] ?: 'PR-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-S-' . substr(md5(uniqid()), 0, 5);

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
                            'first_half_credit_date'  => $entry['first_half_credit_date'] ?? null,
                            'second_half_credit_date' => $entry['second_half_credit_date'] ?? null,
                            'credit_date'             => null,
                            'release_id'              => null,
                            'is_primary'              => true,
                        ]
                    );

                    foreach ($items as $itemData) {
                        $this->upsertItem($batch, $itemData, $parsed['month'], $parsed['year']);
                    }
                    $this->recalcTotals($batch, $parsed['items']);
                    $primaryBatches[] = $batch;

                } catch (\Throwable $e) {
                    return back()->withErrors(["types.{$i}.base64" => "[{$label}] " . $e->getMessage()]);
                }

            } else {
                // Non-monthly: one or more monthly entries (release group when > 1)
                $fileEntries = $entry['entries'];
                $isRelease   = count($fileEntries) > 1;
                $releaseId   = $isRelease ? (string) Str::uuid() : null;

                foreach ($fileEntries as $ei => $fileEntry) {
                    $isPrimary   = ($ei === 0);
                    $pm          = (int) $fileEntry['period_month'];
                    $py          = (int) $fileEntry['period_year'];
                    $periodStart = sprintf('%04d-%02d-01', $py, $pm);
                    $periodEnd   = date('Y-m-d', mktime(0, 0, 0, $pm + 1, 0, $py));
                    $monthLabel  = \Carbon\Carbon::createFromDate($py, $pm, 1)->format('M Y');

                    $overrideMeta = array_filter([
                        'payroll_no'   => $isPrimary ? ($entry['payroll_no'] ?? null) : null,
                        'period_start' => $periodStart,
                        'period_end'   => $periodEnd,
                        'month'        => $pm,
                        'year'         => $py,
                    ], fn($v) => $v !== null && $v !== '');

                    try {
                        $parsed = $this->parser->parseMain($fileEntry['base64'], $overrideMeta);
                    } catch (\Throwable $e) {
                        return back()->withErrors(["types.{$i}.entries.{$ei}.base64" => "[{$label} – {$monthLabel}] Could not parse CSV: " . $e->getMessage()]);
                    }

                    if (empty($parsed['items'])) {
                        return back()->withErrors(["types.{$i}.entries.{$ei}.base64" => "[{$label} – {$monthLabel}] No employee rows found."]);
                    }

                    try {
                        $items     = $this->matcher->matchItems($parsed['items']);
                        $s3Key     = $this->storeBase64ToS3($fileEntry['base64'], $fileEntry['filename']);
                        $payrollNo = $parsed['payroll_no'] ?: 'PR-' . $py . '-' . str_pad($pm, 2, '0', STR_PAD_LEFT) . '-' . strtoupper($entry['type'][0]) . '-' . substr(md5(uniqid()), 0, 5);

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
                                'source_main_filename'    => $fileEntry['filename'],
                                'source_main_s3_key'      => $s3Key,
                                'uploaded_by'             => Auth::id(),
                                'status'                  => 'previewed',
                                'first_half_credit_date'  => null,
                                'second_half_credit_date' => null,
                                'credit_date'             => $entry['credit_date'] ?? null,
                                'notes'                   => in_array($entry['type'], ['cash_advance', 'reimbursement'])
                                                                ? ($entry['purpose'] ?? null)
                                                                : null,
                                'release_id'              => $releaseId,
                                'is_primary'              => $isPrimary,
                            ]
                        );

                        foreach ($items as $itemData) {
                            $this->upsertItem($batch, $itemData, $parsed['month'], $parsed['year']);
                        }
                        $this->recalcTotals($batch, $parsed['items']);

                        if ($isPrimary) {
                            $primaryBatches[] = $batch;
                        }

                    } catch (\Throwable $e) {
                        return back()->withErrors(["types.{$i}.entries.{$ei}.base64" => "[{$label} – {$monthLabel}] " . $e->getMessage()]);
                    }
                }
            }
        }

        // Redirect to first primary batch; flash the other primaries (different types)
        $first  = array_shift($primaryBatches);
        $others = array_map(fn($b) => ['id' => $b->id, 'label' => $b->label, 'payroll_no' => $b->payroll_no], $primaryBatches);

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

        $releaseGroup = null;
        if ($batch->release_id) {
            $releaseGroup = PayrollBatch::where('release_id', $batch->release_id)
                ->orderBy('year')->orderBy('month')
                ->get(['id', 'payroll_no', 'label', 'month', 'year', 'is_primary', 'status'])
                ->values();
        }

        return Inertia::render('Payroll/Cashier/Preview', [
            'batch'        => $batch,
            'matched'      => $items->get('matched', collect())->values(),
            'probable'     => $items->get('probable', collect())->values(),
            'unmatched'    => $items->get('unmatched', collect())->values(),
            'users'        => $users,
            'releaseGroup' => $releaseGroup,
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

        // For release groups: collect matched employees across ALL sibling batches (one email per employee)
        if ($batch->release_id) {
            $siblingIds = PayrollBatch::where('release_id', $batch->release_id)->pluck('id');
            // Collect all items from all siblings, deduplicate by matched_user_id
            $items = PayrollItem::whereHas('batches', fn($q) => $q->whereIn('payroll_batches.id', $siblingIds))
                ->whereNotNull('matched_user_id')
                ->whereIn('match_status', ['matched', 'probable', 'manual'])
                ->with('employee:id,name,email,status')
                ->get()
                ->unique('matched_user_id')
                ->values();
        } else {
            $items = PayrollItem::whereHas('batches', fn($q) => $q->where('payroll_batches.id', $batch->id))
                ->whereNotNull('matched_user_id')
                ->whereIn('match_status', ['matched', 'probable', 'manual'])
                ->with('employee:id,name,email,status')
                ->get();
        }

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
                $sendType    = implode('+', (array) $batch->disbursement_type);
                $emailRecord = PayrollEmail::create([
                    'payroll_item_id' => $item->id,
                    'send_type'       => $sendType,
                    'to_email'        => $emp->email,
                    'bcc_email'       => config('mail.payroll_bcc'),
                    'subject'         => $this->emailSubject($batch),
                    'status'          => 'queued',
                ]);
                SendPayslipJob::dispatch($emailRecord->id)->delay(now()->addSeconds($delay++ * 2));
            }
        }

        // Mark all sibling batches in the release group as sending
        if ($batch->release_id) {
            PayrollBatch::where('release_id', $batch->release_id)->update(['status' => 'sending']);
        } else {
            $batch->update(['status' => 'sending']);
        }

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

        // For release groups, track across all siblings (emails can belong to any sibling's items)
        if ($batch->release_id) {
            $siblingIds = PayrollBatch::where('release_id', $batch->release_id)->pluck('id');
            $items = PayrollItem::whereIn('batch_id', $siblingIds)
                ->whereNotNull('matched_user_id')
                ->with(['employee:id,name,email', 'emails' => fn($q) => $q->latest()->limit(1)])
                ->get()
                ->unique('matched_user_id')
                ->values();
        } else {
            $items = PayrollItem::whereHas('batches', fn($q) => $q->where('payroll_batches.id', $batch->id))
                ->whereNotNull('matched_user_id')
                ->with(['employee:id,name,email', 'emails' => fn($q) => $q->latest()->limit(1)])
                ->get();
        }

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
            if ($batch->release_id) {
                PayrollBatch::where('release_id', $batch->release_id)->update(['status' => 'completed']);
            } else {
                $batch->update(['status' => 'completed']);
            }
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

    private function emailSubject(PayrollBatch $batch, ?string $sendType = null): string
    {
        $label = $batch->disbursementLabel($sendType);

        if ($batch->release_id) {
            $siblings  = PayrollBatch::where('release_id', $batch->release_id)
                ->orderBy('year')->orderBy('month')
                ->get(['month', 'year']);
            $first = $siblings->first();
            $last  = $siblings->last();
            $firstStr = \Carbon\Carbon::createFromDate($first->year, $first->month, 1)->format('M Y');
            $lastStr  = \Carbon\Carbon::createFromDate($last->year, $last->month, 1)->format('M Y');
            $period = ($firstStr === $lastStr) ? $firstStr : "{$firstStr}–{$lastStr}";
        } else {
            $period = \Carbon\Carbon::parse($batch->period_start)->format('M Y');
        }

        return "{$label} — {$period} — PSHS-CRC";
    }
}
