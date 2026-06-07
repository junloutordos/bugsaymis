<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use App\Models\Procurement\Rfq;
use App\Models\Procurement\RfqItem;
use App\Models\Procurement\RfqItemQuotation;
use App\Models\Procurement\RfqSupplier;
use App\Services\Procurement\AoqPdfService;
use App\Services\Procurement\RfqPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RfqController extends Controller
{
    public function index(Request $request)
    {
        $this->checkPerm('procurement.rfq.view');
        $user = $request->user();

        $query = Rfq::with(['pr:id,pr_no,assigned_pr_number,purpose', 'creator:id,name'])
            ->latest();

        if (! ($user->isSuperAdmin() || $user->hasPermission('procurement.rfq.evaluate'))) {
            $query->whereHas('pr', fn ($q) => $q->where('requested_by', $user->id)
                ->orWhereIn('id', Rfq::select('procurement_id')
                    ->where('created_by', $user->id)
                )
            );
        }

        $rfqs = $query->get()->map(fn ($r) => [
            'id'             => $r->id,
            'rfq_number'     => $r->rfq_number,
            'rfq_date'       => $r->rfq_date?->toDateString(),
            'status'         => $r->status,
            'status_label'   => $r->statusLabel(),
            'pr_no'          => $r->pr?->assigned_pr_number ?: $r->pr?->pr_no,
            'purpose'        => $r->pr?->purpose,
            'supplier_count' => $r->suppliers()->count(),
            'created_by'     => $r->creator?->only('id', 'name'),
            'awarded_at'     => $r->awarded_at?->toISOString(),
        ]);

        return Inertia::render('Procurements/RFQ/Index', [
            'rfqs'        => $rfqs,
            'currentUser' => [
                'id'          => $user->id,
                'permissions' => [
                    'canCreate'   => $user->hasPermission('procurement.rfq.create'),
                    'canEvaluate' => $user->hasPermission('procurement.rfq.evaluate'),
                ],
            ],
        ]);
    }

    public function show(Rfq $rfq, Request $request)
    {
        $this->checkPerm('procurement.rfq.view');

        $rfq->load([
            'pr.items',
            'pr.requester:id,name',
            'pr.division:id,division_name',
            'creator:id,name',
            'items',
            'suppliers.itemQuotations',
            'suppliers.sentByUser:id,name',
        ]);

        $user = $request->user();

        // Build AoQ matrix: items × suppliers
        $items     = $rfq->items;
        $suppliers = $rfq->suppliers;

        $matrix = $items->map(function ($item) use ($suppliers) {
            $row = [
                'id'          => $item->id,
                'item_no'     => $item->item_no,
                'description' => $item->description,
                'unit'        => $item->unit,
                'quantity'    => $item->quantity,
                'abc'         => (float) $item->abc,
                'quotations'  => [],
            ];
            foreach ($suppliers as $sup) {
                $q = $item->quotations->firstWhere('rfq_supplier_id', $sup->id);
                $row['quotations'][$sup->id] = $q ? [
                    'unit_cost'  => (float) $q->unit_cost,
                    'total_cost' => (float) $q->total_cost,
                    'is_awarded' => (bool) $q->is_awarded,
                ] : null;
            }

            // Find lowest non-zero unit_cost
            $prices = collect($row['quotations'])->filter(fn ($q) => $q && $q['unit_cost'] > 0)->pluck('unit_cost');
            $row['lowest_price'] = $prices->min();

            return $row;
        });

        return Inertia::render('Procurements/RFQ/Show', [
            'rfq'         => [
                'id'              => $rfq->id,
                'rfq_number'      => $rfq->rfq_number,
                'rfq_date'        => $rfq->rfq_date?->toDateString(),
                'validity_days'   => $rfq->validity_days,
                'delivery_place'  => $rfq->delivery_place,
                'payment_terms'   => $rfq->payment_terms,
                'purpose'         => $rfq->purpose,
                'status'          => $rfq->status,
                'status_label'    => $rfq->statusLabel(),
                'closed_at'       => $rfq->closed_at?->toISOString(),
                'awarded_at'      => $rfq->awarded_at?->toISOString(),
                'pr'              => [
                    'id'       => $rfq->pr?->id,
                    'pr_no'    => $rfq->pr?->assigned_pr_number ?: $rfq->pr?->pr_no,
                    'purpose'  => $rfq->pr?->purpose,
                    'division' => $rfq->pr?->division?->only('id', 'division_name'),
                    'items'    => $rfq->pr?->items?->map(fn ($i) => [
                        'id'          => $i->id,
                        'description' => $i->description,
                        'unit'        => $i->unit,
                        'quantity'    => $i->quantity,
                        'unit_cost'   => (float) $i->unit_cost,
                    ])->values(),
                ],
                'creator'         => $rfq->creator?->only('id', 'name'),
                'items'           => $rfq->items->map(fn ($i) => [
                    'id'                   => $i->id,
                    'item_no'              => $i->item_no,
                    'description'          => $i->description,
                    'unit'                 => $i->unit,
                    'quantity'             => $i->quantity,
                    'abc'                  => (float) $i->abc,
                    'procurement_item_id'  => $i->procurement_item_id,
                ])->values(),
                'suppliers'       => $rfq->suppliers->map(fn ($s) => [
                    'id'                     => $s->id,
                    'supplier_name'          => $s->supplier_name,
                    'supplier_address'       => $s->supplier_address,
                    'supplier_tin'           => $s->supplier_tin,
                    'supplier_contact'       => $s->supplier_contact,
                    'sent_at'                => $s->sent_at?->toISOString(),
                    'quotation_received_at'  => $s->quotation_received_at?->toISOString(),
                    'quotation_filename'     => $s->quotation_filename,
                    'quotation_total'        => (float) ($s->quotation_total ?? 0),
                    'remarks'                => $s->remarks,
                    'awarded_items_count'    => $s->awardedItems()->count(),
                ])->values(),
            ],
            'matrix'      => $matrix->values(),
            'currentUser' => [
                'id'          => $user->id,
                'permissions' => [
                    'canUpload'   => $user->hasAnyPermission(['procurement.rfq.upload', 'procurement.rfq.create']) || $user->isSuperAdmin(),
                    'canEvaluate' => $user->hasPermission('procurement.rfq.evaluate') || $user->isSuperAdmin(),
                    'canAward'    => $user->hasPermission('procurement.rfq.award') || $user->isSuperAdmin(),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPerm('procurement.rfq.create');

        $data = $request->validate([
            'procurement_id' => 'required|exists:procurements,id',
            'rfq_date'       => 'required|date',
            'validity_days'  => 'required|integer|min:1|max:365',
            'delivery_place' => 'nullable|string|max:255',
            'payment_terms'  => 'nullable|string|max:255',
        ]);

        $pr = Procurement::findOrFail($data['procurement_id']);
        abort_unless($pr->status === 'approved', 422, 'Only approved PRs can have an RFQ.');
        abort_unless($pr->items()->count() > 0, 422, 'PR has no items.');

        DB::transaction(function () use ($data, $pr, $request) {
            $rfq = Rfq::create([
                'rfq_number'     => Rfq::generateNumber(),
                'procurement_id' => $pr->id,
                'rfq_date'       => $data['rfq_date'],
                'validity_days'  => $data['validity_days'],
                'delivery_place' => $data['delivery_place'] ?? $pr->division?->division_name,
                'payment_terms'  => $data['payment_terms'] ?? '30 days after acceptance',
                'purpose'        => $pr->purpose,
                'status'         => Rfq::STATUS_DRAFT,
                'created_by'     => $request->user()->id,
            ]);

            foreach ($pr->items as $i => $item) {
                RfqItem::create([
                    'rfq_id'                => $rfq->id,
                    'procurement_item_id'   => $item->id,
                    'item_no'               => $i + 1,
                    'unit'                  => $item->unit,
                    'description'           => $item->description,
                    'quantity'              => $item->quantity,
                    'abc'                   => $item->unit_cost * $item->quantity,
                ]);
            }
        });

        return back()->with('success', 'RFQ created.');
    }

    public function update(Request $request, Rfq $rfq)
    {
        $this->checkPerm('procurement.rfq.create');
        abort_unless($rfq->isEditable(), 422, 'RFQ can only be edited in draft status.');

        $data = $request->validate([
            'rfq_date'      => 'required|date',
            'validity_days' => 'required|integer|min:1|max:365',
            'delivery_place'=> 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:255',
        ]);

        $rfq->update($data);

        return back()->with('success', 'RFQ updated.');
    }

    public function addSupplier(Request $request, Rfq $rfq)
    {
        $this->checkPerm('procurement.rfq.create');
        abort_unless($rfq->status === Rfq::STATUS_DRAFT, 422, 'Suppliers can only be added in draft status.');

        $data = $request->validate([
            'supplier_name'    => 'required|string|max:255',
            'supplier_address' => 'nullable|string|max:500',
            'supplier_tin'     => 'nullable|string|max:50',
            'supplier_contact' => 'nullable|string|max:100',
        ]);

        $supplier = $rfq->suppliers()->create($data);

        return response()->json(['supplier' => $supplier], 201);
    }

    public function removeSupplier(Rfq $rfq, RfqSupplier $supplier)
    {
        $this->checkPerm('procurement.rfq.create');
        abort_unless($rfq->isEditable(), 422, 'Cannot remove supplier at this stage.');
        abort_unless($supplier->rfq_id === $rfq->id, 404);

        if ($supplier->quotation_file_s3_key) {
            Storage::disk('s3')->delete($supplier->quotation_file_s3_key);
        }
        $supplier->delete();

        return response()->json(['deleted' => true]);
    }

    public function open(Request $request, Rfq $rfq)
    {
        $this->checkPerm('procurement.rfq.create');
        abort_unless($rfq->status === Rfq::STATUS_DRAFT, 422);
        abort_unless($rfq->suppliers()->count() >= 1, 422, 'Add at least one supplier before opening.');

        $rfq->update(['status' => Rfq::STATUS_OPEN]);

        return back()->with('success', 'RFQ is now open. Distribute the printed forms to suppliers.');
    }

    public function markSent(Request $request, Rfq $rfq, RfqSupplier $supplier)
    {
        $this->checkPerm('procurement.rfq.upload');
        abort_unless($rfq->status === Rfq::STATUS_OPEN, 422);
        abort_unless($supplier->rfq_id === $rfq->id, 404);

        $supplier->update([
            'sent_at' => now(),
            'sent_by' => $request->user()->id,
        ]);

        return response()->json(['ok' => true]);
    }

    public function uploadQuotation(Request $request, Rfq $rfq, RfqSupplier $supplier)
    {
        $this->checkPerm('procurement.rfq.upload');
        abort_unless(in_array($rfq->status, [Rfq::STATUS_OPEN, Rfq::STATUS_CLOSED]), 422);
        abort_unless($supplier->rfq_id === $rfq->id, 404);

        $data = $request->validate([
            'file_base64' => 'required|string',
            'filename'    => 'required|string|max:255',
        ]);

        // Decode base64 data URI
        $raw = $data['file_base64'];
        if (str_contains($raw, ',')) {
            $raw = explode(',', $raw, 2)[1];
        }
        $bytes = base64_decode($raw);
        abort_if($bytes === false || strlen($bytes) < 10, 422, 'Invalid file data.');
        abort_if(strlen($bytes) > 10 * 1024 * 1024, 422, 'File exceeds 10 MB.');

        $ext     = pathinfo($data['filename'], PATHINFO_EXTENSION) ?: 'pdf';
        $s3Key   = "rfq-quotations/{$rfq->id}/supplier-{$supplier->id}.{$ext}";

        // Delete old file if replacing
        if ($supplier->quotation_file_s3_key) {
            Storage::disk('s3')->delete($supplier->quotation_file_s3_key);
        }

        Storage::disk('s3')->put($s3Key, $bytes);

        $supplier->update([
            'quotation_file_s3_key'    => $s3Key,
            'quotation_filename'       => basename($data['filename']),
            'quotation_received_at'    => $supplier->quotation_received_at ?? now(),
        ]);

        return response()->json(['ok' => true, 'filename' => $supplier->quotation_filename]);
    }

    public function saveQuotations(Request $request, Rfq $rfq, RfqSupplier $supplier)
    {
        $this->checkPerm('procurement.rfq.upload');
        abort_unless(in_array($rfq->status, [Rfq::STATUS_OPEN, Rfq::STATUS_CLOSED]), 422);
        abort_unless($supplier->rfq_id === $rfq->id, 404);

        $data = $request->validate([
            'quotations'                => 'required|array',
            'quotations.*.rfq_item_id'  => 'required|integer|exists:rfq_items,id',
            'quotations.*.unit_cost'    => 'required|numeric|min:0',
            'quotations.*.remarks'      => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($data, $supplier, $rfq) {
            foreach ($data['quotations'] as $q) {
                $item = RfqItem::where('rfq_id', $rfq->id)->findOrFail($q['rfq_item_id']);
                $totalCost = $q['unit_cost'] * $item->quantity;

                RfqItemQuotation::updateOrCreate(
                    ['rfq_supplier_id' => $supplier->id, 'rfq_item_id' => $item->id],
                    [
                        'unit_cost'  => $q['unit_cost'],
                        'total_cost' => $totalCost,
                        'remarks'    => $q['remarks'] ?? null,
                        'is_awarded' => false,
                    ]
                );
            }

            $supplier->update(['quotation_received_at' => $supplier->quotation_received_at ?? now()]);
            $supplier->recalculateTotal();
        });

        return back()->with('success', 'Quotation prices saved.');
    }

    public function close(Request $request, Rfq $rfq)
    {
        $this->checkPerm('procurement.rfq.evaluate');
        abort_unless($rfq->status === Rfq::STATUS_OPEN, 422);

        $rfq->update(['status' => Rfq::STATUS_CLOSED, 'closed_at' => now()]);

        return back()->with('success', 'RFQ closed. Proceed with evaluation.');
    }

    public function award(Request $request, Rfq $rfq)
    {
        $this->checkPerm('procurement.rfq.award');
        abort_unless($rfq->status === Rfq::STATUS_CLOSED, 422, 'RFQ must be closed before awarding.');

        $data = $request->validate([
            'awards'                     => 'required|array|min:1',
            'awards.*.rfq_item_id'       => 'required|integer|exists:rfq_items,id',
            'awards.*.rfq_supplier_id'   => 'required|integer|exists:rfq_suppliers,id',
        ]);

        DB::transaction(function () use ($data, $rfq) {
            // Reset all awards for this RFQ
            RfqItemQuotation::whereHas('item', fn ($q) => $q->where('rfq_id', $rfq->id))
                ->update(['is_awarded' => false]);

            foreach ($data['awards'] as $award) {
                // Validate item + supplier belong to this RFQ
                $item = RfqItem::where('rfq_id', $rfq->id)->findOrFail($award['rfq_item_id']);
                $supplier = RfqSupplier::where('rfq_id', $rfq->id)->findOrFail($award['rfq_supplier_id']);

                RfqItemQuotation::updateOrCreate(
                    ['rfq_item_id' => $item->id, 'rfq_supplier_id' => $supplier->id],
                    ['is_awarded' => true]
                );
            }

            $rfq->update(['status' => Rfq::STATUS_AWARDED, 'awarded_at' => now()]);
        });

        return back()->with('success', 'Awards saved. You may now generate Purchase Orders.');
    }

    public function generatePos(Request $request, Rfq $rfq)
    {
        $this->checkPerm('procurement.rfq.award');
        abort_unless($rfq->status === Rfq::STATUS_AWARDED, 422, 'RFQ must be awarded first.');

        $rfq->load(['items', 'suppliers.itemQuotations.item']);

        $created = DB::transaction(function () use ($rfq, $request) {
            $pos = [];

            foreach ($rfq->suppliers as $supplier) {
                $awardedQuotations = $supplier->itemQuotations->where('is_awarded', true);
                if ($awardedQuotations->isEmpty()) continue;

                $total = $awardedQuotations->sum('total_cost');

                $po = PurchaseOrder::create([
                    'po_number'        => '',
                    'po_date'          => today(),
                    'procurement_id'   => $rfq->procurement_id,
                    'supplier_name'    => $supplier->supplier_name,
                    'supplier_address' => $supplier->supplier_address,
                    'supplier_tin'     => $supplier->supplier_tin,
                    'delivery_place'   => $rfq->delivery_place,
                    'payment_terms'    => $rfq->payment_terms,
                    'delivery_terms'   => "{$rfq->validity_days} days from receipt of PO",
                    'total_amount'     => $total,
                    'status'           => 'draft',
                    'created_by'       => $request->user()->id,
                ]);

                // Auto-number
                $year  = now()->format('Y');
                $month = now()->format('m');
                $seq   = str_pad($po->id, 4, '0', STR_PAD_LEFT);
                $po->update(['po_number' => "PO-{$year}-{$month}-{$seq}"]);

                foreach ($awardedQuotations as $iq) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'item_no'           => $iq->item->item_no,
                        'unit'              => $iq->item->unit,
                        'description'       => $iq->item->description,
                        'quantity'          => $iq->item->quantity,
                        'unit_cost'         => $iq->unit_cost,
                        'total_cost'        => $iq->total_cost,
                    ]);
                }

                $pos[] = $po->po_number;
            }

            return $pos;
        });

        $list = implode(', ', $created);

        return back()->with('success', "Generated " . count($created) . " Purchase Order(s): {$list}.");
    }

    public function downloadQuotation(Rfq $rfq, RfqSupplier $supplier)
    {
        $this->checkPerm('procurement.rfq.view');
        abort_unless($supplier->rfq_id === $rfq->id, 404);
        abort_unless($supplier->quotation_file_s3_key, 404, 'No file uploaded.');

        $bytes    = Storage::disk('s3')->get($supplier->quotation_file_s3_key);
        $filename = $supplier->quotation_filename ?: 'quotation.pdf';
        $mime     = Storage::disk('s3')->mimeType($supplier->quotation_file_s3_key) ?: 'application/octet-stream';

        return response($bytes, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function printRfq(Rfq $rfq, RfqPdfService $pdf): StreamedResponse
    {
        $this->checkPerm('procurement.rfq.view');
        return $pdf->streamRfq($rfq);
    }

    public function printAoq(Rfq $rfq, AoqPdfService $pdf): StreamedResponse
    {
        $this->checkPerm('procurement.rfq.evaluate');
        return $pdf->streamAoq($rfq);
    }

    public function printNoa(Rfq $rfq, RfqSupplier $supplier, RfqPdfService $pdf): StreamedResponse
    {
        $this->checkPerm('procurement.rfq.award');
        abort_unless($supplier->rfq_id === $rfq->id, 404);
        return $pdf->streamNoa($rfq, $supplier);
    }

    private function checkPerm(string $permission): void
    {
        abort_unless(
            request()->user()->hasPermission($permission) || request()->user()->isSuperAdmin(),
            403
        );
    }
}
