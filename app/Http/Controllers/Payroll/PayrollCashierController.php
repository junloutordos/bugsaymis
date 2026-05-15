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

    // ── Parse & store ─────────────────────────────────────────────────────────

    public function upload(Request $request)
    {
        $this->authorize('payroll.upload');

        $request->validate([
            'main_file_base64'  => 'required|string',
            'main_filename'     => 'required|string|max:255',
            'bonus_file_base64' => 'nullable|string',
            'bonus_filename'    => 'nullable|string|max:255',
            'sheet_name'        => 'nullable|string|max:100',
        ]);

        $user = Auth::user();

        // Parse main file
        $parsed = $this->parser->parseMain(
            $request->input('main_file_base64'),
            $request->input('sheet_name')
        );

        // Parse bonus file if provided
        $bonusData = [];
        if ($request->filled('bonus_file_base64')) {
            $bonusData = $this->parser->parseBonus($request->input('bonus_file_base64'));
        }

        // Merge bonus fields into items
        $items = $this->mergeBonusIntoItems($parsed['items'], $bonusData);

        // Match employees
        $items = $this->matcher->matchItems($items);

        // Store Excel on S3
        $mainKey  = $this->storeBase64ToS3($request->input('main_file_base64'), $request->input('main_filename'));
        $bonusKey = $request->filled('bonus_file_base64')
            ? $this->storeBase64ToS3($request->input('bonus_file_base64'), $request->input('bonus_filename', 'bonus.xlsx'))
            : null;

        // Create batch (idempotent on payroll_no)
        $batch = PayrollBatch::updateOrCreate(
            ['payroll_no' => $parsed['payroll_no']],
            [
                'batch_type'            => 'main',
                'period_start'          => $parsed['period_start'],
                'period_end'            => $parsed['period_end'],
                'month'                 => $parsed['month'],
                'year'                  => $parsed['year'],
                'fund_cluster'          => $parsed['fund_cluster'],
                'entity_name'           => $parsed['entity_name'],
                'source_main_filename'  => $request->input('main_filename'),
                'source_bonus_filename' => $request->input('bonus_filename'),
                'source_main_s3_key'    => $mainKey,
                'source_bonus_s3_key'   => $bonusKey,
                'uploaded_by'           => $user->id,
                'status'                => 'previewed',
            ]
        );

        // Upsert items (one per employee per month)
        foreach ($items as $itemData) {
            $this->upsertItem($batch, $itemData, $parsed['month'], $parsed['year']);
        }

        // Recompute totals
        $this->recalcTotals($batch);

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

            // Enforce: this item belongs to this batch's month/year
            abort_if($item->month !== $batch->month || $item->year !== $batch->year, 403);

            $item->update([
                'matched_user_id' => $res['user_id'],
                'match_status'    => 'manual',
            ]);

            if (!empty($res['save_alias'])) {
                $this->matcher->saveAlias(
                    $item->employee_name_raw,
                    $res['user_id'],
                    Auth::id()
                );
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

        $items = PayrollItem::where('month', $batch->month)
            ->where('year', $batch->year)
            ->whereNotNull('matched_user_id')
            ->whereIn('match_status', ['matched', 'probable', 'manual'])
            ->with('employee:id,name,email,status,email_verified_at')
            ->get();

        foreach ($items as $item) {
            $emp = $item->employee;
            if (!$emp || $emp->status === 'inactive' || !$emp->email_verified_at) {
                continue;
            }

            // Create queued email record
            $emailRecord = PayrollEmail::create([
                'payroll_item_id' => $item->id,
                'send_type'       => 'initial',
                'to_email'        => $emp->email,
                'bcc_email'       => config('mail.payroll_bcc'),
                'subject'         => $this->emailSubject($batch),
                'status'          => 'queued',
            ]);

            SendPayslipJob::dispatch($emailRecord->id)->delay(
                now()->addSeconds($items->search($item) * 2)
            );
        }

        $batch->update(['status' => 'sending']);

        return back()->with('success', 'Payslips queued for sending.');
    }

    // ── Send status (polling) ─────────────────────────────────────────────────

    public function status(PayrollBatch $batch)
    {
        $this->authorize('payroll.view_all');

        $items = PayrollItem::where('month', $batch->month)
            ->where('year', $batch->year)
            ->whereNotNull('matched_user_id')
            ->with(['employee:id,name,email', 'emails' => fn($q) => $q->latest()->limit(1)])
            ->get();

        $counts = [
            'total'   => $items->count(),
            'queued'  => 0, 'sending' => 0,
            'sent'    => 0, 'failed'  => 0,
        ];

        $rows = $items->map(function ($item) use (&$counts) {
            $latest = $item->emails->first();
            $status = $latest?->status ?? 'unsent';
            if (isset($counts[$status])) $counts[$status]++;
            return [
                'id'         => $item->id,
                'name'       => $item->employee?->name ?? $item->employee_name_raw,
                'email'      => $item->employee?->email,
                'email_status' => $status,
                'sent_at'    => $latest?->sent_at,
                'last_error' => $latest?->last_error,
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
            ->whereHas('item', fn($q) => $q->where('month', $batch->month)->where('year', $batch->year))
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

    // ── Bonus upload ──────────────────────────────────────────────────────────

    public function uploadBonusForm(PayrollBatch $batch)
    {
        $this->authorize('payroll.upload');
        return Inertia::render('Payroll/Cashier/UploadBonus', [
            'batch' => $batch->only(['id', 'payroll_no', 'period_start', 'period_end', 'month', 'year']),
        ]);
    }

    public function uploadBonus(Request $request, PayrollBatch $batch)
    {
        $this->authorize('payroll.upload');

        $request->validate([
            'bonus_file_base64' => 'required|string',
            'bonus_filename'    => 'required|string|max:255',
        ]);

        $bonusData = $this->parser->parseBonus($request->input('bonus_file_base64'));
        $bonusKey  = $this->storeBase64ToS3(
            $request->input('bonus_file_base64'),
            $request->input('bonus_filename')
        );

        // Create a bonus batch record
        $bonusBatch = PayrollBatch::create([
            'payroll_no'            => $batch->payroll_no . '-BONUS',
            'batch_type'            => 'bonus',
            'period_start'          => $batch->period_start,
            'period_end'            => $batch->period_end,
            'month'                 => $batch->month,
            'year'                  => $batch->year,
            'fund_cluster'          => $batch->fund_cluster,
            'entity_name'           => $batch->entity_name,
            'source_bonus_filename' => $request->input('bonus_filename'),
            'source_bonus_s3_key'   => $bonusKey,
            'uploaded_by'           => Auth::id(),
            'status'                => 'completed',
        ]);

        // Update existing payroll items for this month/year with bonus fields
        $updated = 0;
        foreach ($bonusData as $normalizedName => $bonusFields) {
            $alias   = \App\Models\Payroll\PayrollNameAlias::where('alias_name', $normalizedName)->first();
            $userId  = $alias?->user_id
                ?? User::where('status', '<>', 'inactive')->get()
                       ->first(fn($u) => $this->parser->normalizeName($u->name) === $normalizedName)
                       ?->id;

            if (!$userId) continue;

            PayrollItem::where('month', $batch->month)
                ->where('year', $batch->year)
                ->where('matched_user_id', $userId)
                ->update(array_merge($bonusFields, [
                    'bonus_batch_id'   => $bonusBatch->id,
                    'bonus_uploaded_at'=> now(),
                ]));
            $updated++;
        }

        // Queue bonus notification emails
        $items = PayrollItem::where('month', $batch->month)
            ->where('year', $batch->year)
            ->whereNotNull('bonus_uploaded_at')
            ->whereNotNull('matched_user_id')
            ->with('employee:id,name,email,status,email_verified_at')
            ->get();

        foreach ($items as $item) {
            $emp = $item->employee;
            if (!$emp || $emp->status === 'inactive' || !$emp->email_verified_at) continue;

            $emailRecord = PayrollEmail::create([
                'payroll_item_id' => $item->id,
                'send_type'       => 'bonus_update',
                'to_email'        => $emp->email,
                'bcc_email'       => config('mail.payroll_bcc'),
                'subject'         => '[Updated] ' . $this->emailSubject($batch),
                'status'          => 'queued',
            ]);

            SendPayslipJob::dispatch($emailRecord->id)->delay(
                now()->addSeconds($items->search($item) * 2)
            );
        }

        return back()->with('success', "Bonus data uploaded. {$updated} payslips updated and notifications queued.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function upsertItem(PayrollBatch $batch, array $data, int $month, int $year): void
    {
        PayrollItem::updateOrCreate(
            [
                'year'            => $year,
                'month'           => $month,
                'matched_user_id' => $data['matched_user_id'] ?? null,
                // For unmatched rows, also match on raw name to avoid duplication
                ...($data['matched_user_id'] ? [] : ['employee_name_raw' => $data['employee_name_raw']]),
            ],
            array_merge($data, [
                'batch_id' => $batch->id,
                'month'    => $month,
                'year'     => $year,
            ])
        );
    }

    private function mergeBonusIntoItems(array $items, array $bonusData): array
    {
        return array_map(function ($item) use ($bonusData) {
            $key = $this->parser->normalizeName($item['employee_name_raw'] ?? '');
            if (isset($bonusData[$key])) {
                $item = array_merge($item, $bonusData[$key]);
            }
            return $item;
        }, $items);
    }

    private function recalcTotals(PayrollBatch $batch): void
    {
        $totals = PayrollItem::where('batch_id', $batch->id)
            ->selectRaw('SUM(gross_earnings) as g, SUM(total_deductions) as d, SUM(net_pay) as n')
            ->first();

        $batch->update([
            'totals_gross'       => $totals->g ?? 0,
            'totals_deductions'  => $totals->d ?? 0,
            'totals_net'         => $totals->n ?? 0,
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

    private function emailSubject(PayrollBatch $batch): string
    {
        $start = \Carbon\Carbon::parse($batch->period_start)->format('M j');
        $end   = \Carbon\Carbon::parse($batch->period_end)->format('M j, Y');
        return "Payslip for {$start}–{$end} — PSHS-CRC";
    }
}
