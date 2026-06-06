<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement\DisbursementVoucher;
use App\Models\Procurement\OblRequest;
use App\Services\Procurement\DVService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DisbursementVoucherController extends Controller
{
    public function __construct(private DVService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $canViewAll = $user->isSuperAdmin() || $user->hasAnyPermission([
            'procurement.dv.delivery', 'procurement.dv.bookkeep',
            'procurement.dv.account', 'procurement.dv.ocd_sign', 'procurement.dv.cashier',
        ]);

        $query = DisbursementVoucher::with(['creator:id,name', 'ors:id,ors_number'])->latest();
        if (! $canViewAll) {
            $query->where('created_by', $user->id);
        }

        // Pending-my-action
        $pendingQuery = DisbursementVoucher::with(['creator:id,name'])->latest();
        if ($user->hasPermission('procurement.dv.delivery')) {
            $pendingQuery->where('status', 'pending_delivery');
        } elseif ($user->hasPermission('procurement.dv.bookkeep')) {
            $pendingQuery->whereIn('status', ['pending_bookkeeper', 'returned_accountant']);
        } elseif ($user->hasPermission('procurement.dv.account')) {
            $pendingQuery->where('status', 'pending_accountant');
        } elseif ($user->hasPermission('procurement.dv.ocd_sign')) {
            $pendingQuery->whereIn('status', ['pending_ocd_sign', 'pending_ocd_payment']);
        } elseif ($user->hasPermission('procurement.dv.cashier')) {
            $pendingQuery->where('status', 'pending_cashier');
        } else {
            $pendingQuery->whereRaw('0=1');
        }

        return Inertia::render('Procurements/DV/Index', [
            'dvRecords'     => $query->get()->map(fn ($d) => $this->formatDV($d)),
            'pendingAction' => $pendingQuery->get()->map(fn ($d) => $this->formatDV($d)),
            'orsRecords'    => OblRequest::whereIn('status', ['completed', 'at_canvaser'])
                ->orderByDesc('id')
                ->get(['id', 'ors_number', 'activity_title', 'amount'])
                ->map(fn ($o) => ['id' => $o->id, 'label' => "{$o->ors_number} — {$o->activity_title}"]),
            'currentUser'   => [
                'id'          => $user->id,
                'permissions' => [
                    'canCreate'   => $user->hasPermission('procurement.dv.create'),
                    'canDelivery' => $user->hasPermission('procurement.dv.delivery'),
                    'canBookkeep' => $user->hasPermission('procurement.dv.bookkeep'),
                    'canAccount'  => $user->hasPermission('procurement.dv.account'),
                    'canOcdSign'  => $user->hasPermission('procurement.dv.ocd_sign'),
                    'canCashier'  => $user->hasPermission('procurement.dv.cashier'),
                ],
            ],
        ]);
    }

    public function show(DisbursementVoucher $dv, Request $request)
    {
        $user = $request->user();

        $canView = $dv->created_by === $user->id
            || $user->isSuperAdmin()
            || $user->hasAnyPermission([
                'procurement.dv.delivery', 'procurement.dv.bookkeep',
                'procurement.dv.account', 'procurement.dv.ocd_sign', 'procurement.dv.cashier',
            ]);
        abort_unless($canView, 403);

        $dv->load([
            'ors:id,ors_number,amount,activity_title',
            'creator:id,name', 'supplyOfficer:id,name',
            'preparedByUser:id,name', 'divisionChief:id,name', 'divisionClerk:id,name',
            'bookkeeper:id,name', 'accountant:id,name',
            'ocdSigner:id,name', 'cashier:id,name',
        ]);

        return Inertia::render('Procurements/DV/Show', [
            'dv'          => $this->formatDV($dv, detailed: true),
            'currentUser' => [
                'id'          => $user->id,
                'permissions' => [
                    'canDelivery'  => $user->hasPermission('procurement.dv.delivery'),
                    'canPrepare'   => $dv->created_by === $user->id || $user->isSuperAdmin(),
                    'canDcElog'    => $user->hasPermission('procurement.pr.dc_sign') || $user->isSuperAdmin(),
                    'canForward'   => $dv->created_by === $user->id || $user->isSuperAdmin(),
                    'canBookkeep'  => $user->hasPermission('procurement.dv.bookkeep'),
                    'canAccount'   => $user->hasPermission('procurement.dv.account'),
                    'canOcdSign'   => $user->hasPermission('procurement.dv.ocd_sign'),
                    'canCashier'   => $user->hasPermission('procurement.dv.cashier'),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('procurement.dv.create') || $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'ors_id'         => 'nullable|exists:obligation_requests,id',
            'po_number'      => 'nullable|string|max:50',
            'activity_title' => 'nullable|string|max:200',
            'activity_date'  => 'nullable|date',
            'gross_amount'   => 'required|numeric|min:0',
            'tax_amount'     => 'nullable|numeric|min:0',
        ]);

        $gross = (float) $data['gross_amount'];
        $tax   = (float) ($data['tax_amount'] ?? 0);

        $dv = DisbursementVoucher::create(array_merge($data, [
            'created_by' => $user->id,
            'net_amount' => $gross - $tax,
            'tax_amount' => $tax,
            'status'     => 'draft',
        ]));

        $year = now()->format('Y');
        $seq  = str_pad($dv->id, 4, '0', STR_PAD_LEFT);
        $dv->update(['dv_number' => "DV-{$year}-{$seq}"]);

        return back()->with('success', 'Disbursement Voucher created.');
    }

    // ── Phase A: Delivery ─────────────────────────────────────────────────────

    public function recordDelivery(Request $request, DisbursementVoucher $dv)
    {
        abort_unless($request->user()->hasPermission('procurement.dv.delivery') || $request->user()->isSuperAdmin(), 403);
        abort_unless($dv->status === 'draft', 422);

        $data = $request->validate([
            'iar_number' => 'nullable|string|max:50',
            'ris_number' => 'nullable|string|max:50',
            'dr_number'  => 'nullable|string|max:50',
            'ris_complete' => 'boolean',
        ]);

        $this->service->recordDelivery($dv, $request->user(), $data);

        return back()->with('success', 'Delivery recorded and accepted.');
    }

    // ── Phase B: DV Preparation ───────────────────────────────────────────────

    public function prepareDv(Request $request, DisbursementVoucher $dv)
    {
        $user = $request->user();
        abort_unless($dv->created_by === $user->id || $user->isSuperAdmin(), 403);
        abort_unless(in_array($dv->status, ['delivery_accepted', 'returned_bookkeeper', 'returned_accountant']), 422);

        $data = $request->validate([
            'gross_amount'   => 'required|numeric|min:0',
            'tax_amount'     => 'nullable|numeric|min:0',
            'activity_title' => 'nullable|string|max:200',
            'activity_date'  => 'nullable|date',
            'po_number'      => 'nullable|string|max:50',
        ]);

        $gross = (float) $data['gross_amount'];
        $tax   = (float) ($data['tax_amount'] ?? 0);

        $this->service->prepareDv($dv, $user, array_merge($data, ['net_amount' => $gross - $tax]));

        return back()->with('success', 'DV details saved.');
    }

    public function dcElog(Request $request, DisbursementVoucher $dv)
    {
        $user = $request->user();
        abort_unless($user->hasPermission('procurement.pr.dc_sign') || $user->isSuperAdmin(), 403);
        abort_unless($dv->status === 'preparing', 422);

        $this->service->dcElog($dv, $user);

        return back()->with('success', 'Division Chief logged in eLog.');
    }

    public function forwardToBookkeeper(Request $request, DisbursementVoucher $dv)
    {
        $user = $request->user();
        abort_unless($dv->created_by === $user->id || $user->isSuperAdmin(), 403);
        abort_unless($dv->status === 'preparing', 422);
        abort_unless($dv->division_chief_elog_at !== null, 422, 'Division Chief must log in eLog first.');

        $this->service->forwardToBookkeeper($dv, $user);

        return back()->with('success', 'DV forwarded to Bookkeeper.');
    }

    // ── Phase C: Bookkeeper ───────────────────────────────────────────────────

    public function bookkeeperAction(Request $request, DisbursementVoucher $dv)
    {
        abort_unless($request->user()->hasPermission('procurement.dv.bookkeep') || $request->user()->isSuperAdmin(), 403);
        abort_unless($dv->status === 'pending_bookkeeper', 422);

        $data = $request->validate([
            'action'  => 'required|in:forward,return',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->service->bookkeeperAction($dv, $request->user(), $data['action'], $data['remarks'] ?? '');

        $msg = $data['action'] === 'forward' ? 'DV forwarded to Accountant.' : 'DV returned — incomplete documents.';

        return back()->with('success', $msg);
    }

    // ── Phase D: Accountant ───────────────────────────────────────────────────

    public function accountantAction(Request $request, DisbursementVoucher $dv)
    {
        abort_unless($request->user()->hasPermission('procurement.dv.account') || $request->user()->isSuperAdmin(), 403);
        abort_unless($dv->status === 'pending_accountant', 422);

        $data = $request->validate([
            'action'  => 'required|in:forward,return',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->service->accountantAction($dv, $request->user(), $data['action'], $data['remarks'] ?? '');

        $msg = $data['action'] === 'forward' ? 'DV forwarded to Campus Director for signing.' : 'DV returned — data error.';

        return back()->with('success', $msg);
    }

    // ── Phase E: OCD Sign ─────────────────────────────────────────────────────

    public function ocdSign(Request $request, DisbursementVoucher $dv)
    {
        abort_unless($request->user()->hasPermission('procurement.dv.ocd_sign') || $request->user()->isSuperAdmin(), 403);
        abort_unless($dv->status === 'pending_ocd_sign', 422);

        $this->service->ocdSign($dv, $request->user());

        return back()->with('success', 'DV signed by Campus Director.');
    }

    // ── Phase F: Cashier ──────────────────────────────────────────────────────

    public function cashierProcess(Request $request, DisbursementVoucher $dv)
    {
        abort_unless($request->user()->hasPermission('procurement.dv.cashier') || $request->user()->isSuperAdmin(), 403);
        abort_unless($dv->status === 'pending_cashier', 422);

        $data = $request->validate([
            'payment_method' => 'required|in:ada,cheque',
            'cashier_amount' => 'required|numeric|min:0',
        ]);

        $this->service->cashierProcess($dv, $request->user(), $data['payment_method'], (float) $data['cashier_amount']);

        return back()->with('success', 'Payment processed and forwarded to Campus Director for signature.');
    }

    // ── Phase G: OCD Payment Sign ─────────────────────────────────────────────

    public function ocdPaymentSign(Request $request, DisbursementVoucher $dv)
    {
        abort_unless($request->user()->hasPermission('procurement.dv.ocd_sign') || $request->user()->isSuperAdmin(), 403);
        abort_unless($dv->status === 'pending_ocd_payment', 422);

        $this->service->ocdPaymentSign($dv, $request->user());

        return back()->with('success', 'Payment signed by Campus Director — released to supplier.');
    }

    // ── Format helper ─────────────────────────────────────────────────────────

    private function formatDV(DisbursementVoucher $d, bool $detailed = false): array
    {
        $base = [
            'id'             => $d->id,
            'dv_number'      => $d->dv_number,
            'po_number'      => $d->po_number,
            'activity_title' => $d->activity_title,
            'gross_amount'   => $d->gross_amount,
            'net_amount'     => $d->net_amount,
            'payment_method' => $d->payment_method,
            'status'         => $d->status,
            'status_label'   => $d->statusLabel(),
            'creator'        => $d->creator?->only('id', 'name'),
            'ors'            => $d->ors?->only('id', 'ors_number'),
            'created_at'     => $d->created_at?->toDateString(),
        ];

        if ($detailed) {
            $base = array_merge($base, [
                'activity_date'           => $d->activity_date?->toDateString(),
                'tax_amount'              => $d->tax_amount,
                'iar_number'              => $d->iar_number,
                'ris_number'              => $d->ris_number,
                'dr_number'               => $d->dr_number,
                'ris_complete'            => $d->ris_complete,
                'delivery_accepted_at'    => $d->delivery_accepted_at?->toISOString(),
                'supply_officer'          => $d->supplyOfficer?->only('id', 'name'),
                'dv_prepared_at'          => $d->dv_prepared_at?->toISOString(),
                'prepared_by'             => $d->preparedByUser?->only('id', 'name'),
                'division_chief'          => $d->divisionChief?->only('id', 'name'),
                'division_chief_elog_at'  => $d->division_chief_elog_at?->toISOString(),
                'division_clerk'          => $d->divisionClerk?->only('id', 'name'),
                'division_clerk_at'       => $d->division_clerk_at?->toISOString(),
                'bookkeeper'              => $d->bookkeeper?->only('id', 'name'),
                'bookkeeper_at'           => $d->bookkeeper_at?->toISOString(),
                'bookkeeper_remarks'      => $d->bookkeeper_remarks,
                'bookkeeper_returned_at'  => $d->bookkeeper_returned_at?->toISOString(),
                'bookkeeper_return_reason' => $d->bookkeeper_return_reason,
                'accountant'              => $d->accountant?->only('id', 'name'),
                'accountant_at'           => $d->accountant_at?->toISOString(),
                'accountant_remarks'      => $d->accountant_remarks,
                'accountant_returned_at'  => $d->accountant_returned_at?->toISOString(),
                'accountant_return_reason' => $d->accountant_return_reason,
                'ocd_signer'              => $d->ocdSigner?->only('id', 'name'),
                'ocd_sign_at'             => $d->ocd_sign_at?->toISOString(),
                'cashier'                 => $d->cashier?->only('id', 'name'),
                'cashier_processed_at'    => $d->cashier_processed_at?->toISOString(),
                'cashier_amount'          => $d->cashier_amount,
                'ocd_payment_sign_at'     => $d->ocd_payment_sign_at?->toISOString(),
                'payment_released_at'     => $d->payment_released_at?->toISOString(),
                'payment_reference'       => $d->payment_reference,
            ]);
        }

        return $base;
    }
}
