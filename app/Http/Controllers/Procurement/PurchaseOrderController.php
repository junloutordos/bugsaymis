<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Services\Procurement\PurchaseOrderPdfService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderPdfService $pdf) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasAnyPermission(['procurement.po.view', 'procurement.po.create',
            'procurement.po.review', 'procurement.po.sign']) || $user->isSuperAdmin(), 403);

        $canViewAll = $user->isSuperAdmin() || $user->hasAnyPermission([
            'procurement.po.review', 'procurement.po.sign',
        ]);

        $query = PurchaseOrder::with(['creator:id,name', 'pr:id,pr_no,purpose',
            'procurementOfficer:id,name', 'ocd:id,name'])
            ->latest();

        if (! $canViewAll) {
            $query->where('created_by', $user->id);
        }

        // Pending-my-action
        $pendingQuery = PurchaseOrder::with(['creator:id,name', 'pr:id,pr_no'])->latest();
        if ($user->hasPermission('procurement.po.review')) {
            $pendingQuery->where('status', 'pending_procurement');
        } elseif ($user->hasPermission('procurement.po.sign')) {
            $pendingQuery->where('status', 'pending_ocd');
        } else {
            $pendingQuery->whereRaw('0=1');
        }

        return Inertia::render('Procurements/PO/Index', [
            'poRecords'     => $query->get()->map(fn ($po) => $this->format($po)),
            'pendingAction' => $pendingQuery->get()->map(fn ($po) => $this->format($po)),
            'prs'           => Procurement::where('status', 'approved')
                ->orderByDesc('id')
                ->get(['id', 'pr_no', 'purpose'])
                ->map(fn ($p) => ['id' => $p->id, 'label' => "{$p->pr_no} — {$p->purpose}"]),
            'currentUser'   => [
                'id'          => $user->id,
                'permissions' => [
                    'canCreate' => $user->hasAnyPermission(['procurement.po.create']) || $user->isSuperAdmin(),
                    'canReview' => $user->hasPermission('procurement.po.review'),
                    'canSign'   => $user->hasPermission('procurement.po.sign'),
                ],
            ],
        ]);
    }

    public function show(PurchaseOrder $po, Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $po->created_by === $user->id
            || $user->isSuperAdmin()
            || $user->hasAnyPermission(['procurement.po.view', 'procurement.po.review', 'procurement.po.sign']),
            403
        );

        $po->loadMissing(['creator:id,name', 'pr.items', 'procurementOfficer:id,name',
            'ocd:id,name', 'items', 'obligationRequests:id,ors_number,status,purchase_order_id']);

        return Inertia::render('Procurements/PO/Show', [
            'po'          => $this->formatFull($po),
            'currentUser' => [
                'id'          => $user->id,
                'permissions' => [
                    'canEdit'   => $po->created_by === $user->id || $user->isSuperAdmin(),
                    'canReview' => $user->hasPermission('procurement.po.review'),
                    'canSign'   => $user->hasPermission('procurement.po.sign'),
                ],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('procurement.po.create') || $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'procurement_id'  => 'nullable|exists:procurements,id',
            'supplier_name'   => 'required|string|max:200',
            'supplier_address'=> 'nullable|string|max:500',
            'supplier_tin'    => 'nullable|string|max:30',
            'delivery_place'  => 'nullable|string|max:200',
            'delivery_date'   => 'nullable|date',
            'payment_terms'   => 'nullable|string|max:100',
            'delivery_terms'  => 'nullable|string|max:100',
            'remarks'         => 'nullable|string',
        ]);

        $po = PurchaseOrder::create(array_merge($data, [
            'created_by'  => $user->id,
            'po_date'     => today(),
            'status'      => 'draft',
            'total_amount'=> 0,
        ]));

        // Copy PR items as PO line items if PR is linked
        if (! empty($data['procurement_id'])) {
            $pr = Procurement::with('items')->find($data['procurement_id']);
            if ($pr) {
                foreach ($pr->items as $i => $item) {
                    $qty  = (int) ($item->quantity ?? 1);
                    $cost = (float) ($item->unit_cost ?? 0);
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'item_no'           => $i + 1,
                        'unit'              => $item->unit,
                        'description'       => $item->description,
                        'quantity'          => $qty,
                        'unit_cost'         => $cost,
                        'total_cost'        => $qty * $cost,
                    ]);
                }
                $po->recalculateTotal();
            }
        }

        return redirect()->route('po.show', $po->id)->with('success', 'Purchase Order created.');
    }

    public function storeItem(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_unless($po->isEditable(), 403, 'Only draft POs can be edited.');

        $data = $request->validate([
            'unit'        => 'nullable|string|max:50',
            'description' => 'required|string',
            'quantity'    => 'required|integer|min:1',
            'unit_cost'   => 'required|numeric|min:0',
        ]);

        $nextNo = ($po->items()->max('item_no') ?? 0) + 1;
        $total  = $data['quantity'] * $data['unit_cost'];

        PurchaseOrderItem::create(array_merge($data, [
            'purchase_order_id' => $po->id,
            'item_no'           => $nextNo,
            'total_cost'        => $total,
        ]));

        $po->recalculateTotal();

        return back()->with('success', 'Item added.');
    }

    public function destroyItem(PurchaseOrder $po, PurchaseOrderItem $item): RedirectResponse
    {
        abort_unless($po->isEditable(), 403);
        abort_unless($item->purchase_order_id === $po->id, 403);

        $item->delete();
        $po->recalculateTotal();

        // Re-sequence item_no
        $po->items()->orderBy('id')->get()->each(function ($i, $idx) {
            $i->update(['item_no' => $idx + 1]);
        });

        return back()->with('success', 'Item removed.');
    }

    public function submit(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $user = $request->user();
        abort_unless($po->created_by === $user->id || $user->isSuperAdmin(), 403);
        abort_unless($po->status === 'draft', 422, 'Only draft POs can be submitted.');
        abort_unless($po->items()->exists(), 422, 'Add at least one line item before submitting.');

        $po->update(['status' => 'pending_procurement']);

        return back()->with('success', 'PO submitted for Procurement Officer review.');
    }

    public function procurementReview(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('procurement.po.review') || $request->user()->isSuperAdmin(), 403);
        abort_unless($po->status === 'pending_procurement', 422);

        $data = $request->validate([
            'action'   => 'required|in:approve,return',
            'remarks'  => 'nullable|string',
        ]);

        if ($data['action'] === 'return') {
            $po->update([
                'status'                       => 'draft',
                'procurement_officer_id'       => $request->user()->id,
                'procurement_officer_at'       => now(),
                'procurement_officer_remarks'  => $data['remarks'],
            ]);
            return back()->with('success', 'PO returned to creator for revision.');
        }

        $po->update([
            'status'                       => 'pending_ocd',
            'procurement_officer_id'       => $request->user()->id,
            'procurement_officer_at'       => now(),
            'procurement_officer_remarks'  => $data['remarks'],
        ]);

        return back()->with('success', 'PO forwarded to Campus Director for signature.');
    }

    public function ocdSign(Request $request, PurchaseOrder $po): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('procurement.po.sign') || $request->user()->isSuperAdmin(), 403);
        abort_unless($po->status === 'pending_ocd', 422);

        $data = $request->validate([
            'action'  => 'required|in:sign,return',
            'remarks' => 'nullable|string',
        ]);

        if ($data['action'] === 'return') {
            $po->update([
                'status'  => 'pending_procurement',
                'remarks' => $data['remarks'],
            ]);
            return back()->with('success', 'PO returned to Procurement Officer.');
        }

        // Generate PO number on issue: PO-YYYY-MM-XXXX
        $poNumber = $this->generatePoNumber();

        $po->update([
            'status'     => 'issued',
            'po_number'  => $poNumber,
            'ocd_id'     => $request->user()->id,
            'ocd_at'     => now(),
            'issued_at'  => now(),
        ]);

        return back()->with('success', "PO issued — {$poNumber}.");
    }

    public function cancel(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            ($po->created_by === $user->id && $po->status === 'draft') || $user->isSuperAdmin(),
            403
        );

        $po->update(['status' => 'cancelled']);

        return redirect()->route('po.index')->with('success', 'Purchase Order cancelled.');
    }

    public function print(PurchaseOrder $po)
    {
        $user = request()->user();
        abort_unless(
            $po->created_by === $user->id
            || $user->isSuperAdmin()
            || $user->hasAnyPermission(['procurement.po.view', 'procurement.po.review', 'procurement.po.sign']),
            403
        );

        return $this->pdf->stream($po);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Y-m');
        $last   = PurchaseOrder::where('po_number', 'like', $prefix . '-%')
            ->orderByDesc('po_number')
            ->value('po_number');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function format(PurchaseOrder $po): array
    {
        return [
            'id'            => $po->id,
            'po_number'     => $po->po_number,
            'po_date'       => $po->po_date?->format('Y-m-d'),
            'supplier_name' => $po->supplier_name,
            'total_amount'  => $po->total_amount,
            'status'        => $po->status,
            'status_label'  => $po->statusLabel(),
            'creator'       => ['id' => $po->creator?->id, 'name' => $po->creator?->name],
            'pr'            => $po->pr ? ['id' => $po->pr->id, 'pr_no' => $po->pr->pr_no] : null,
            'created_at'    => $po->created_at?->format('Y-m-d'),
        ];
    }

    private function formatFull(PurchaseOrder $po): array
    {
        return array_merge($this->format($po), [
            'supplier_address'             => $po->supplier_address,
            'supplier_tin'                 => $po->supplier_tin,
            'delivery_place'               => $po->delivery_place,
            'delivery_date'                => $po->delivery_date?->format('Y-m-d'),
            'payment_terms'                => $po->payment_terms,
            'delivery_terms'               => $po->delivery_terms,
            'remarks'                      => $po->remarks,
            'issued_at'                    => $po->issued_at?->format('Y-m-d H:i'),
            'procurement_officer'          => $po->procurementOfficer
                ? ['id' => $po->procurementOfficer->id, 'name' => $po->procurementOfficer->name]
                : null,
            'procurement_officer_at'       => $po->procurement_officer_at?->format('Y-m-d H:i'),
            'procurement_officer_remarks'  => $po->procurement_officer_remarks,
            'ocd'                          => $po->ocd
                ? ['id' => $po->ocd->id, 'name' => $po->ocd->name]
                : null,
            'ocd_at'                       => $po->ocd_at?->format('Y-m-d H:i'),
            'items'                        => $po->items->map(fn ($i) => [
                'id'          => $i->id,
                'item_no'     => $i->item_no,
                'unit'        => $i->unit,
                'description' => $i->description,
                'quantity'    => $i->quantity,
                'unit_cost'   => $i->unit_cost,
                'total_cost'  => $i->total_cost,
            ]),
            'ors_list' => $po->obligationRequests->map(fn ($o) => [
                'id'         => $o->id,
                'ors_number' => $o->ors_number,
                'status'     => $o->status,
            ]),
        ]);
    }
}
