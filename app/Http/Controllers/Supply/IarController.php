<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Supply\IarItem;
use App\Models\Supply\InspectionAcceptanceReport;
use App\Models\Supply\SupplyItem;
use App\Models\User;
use App\Services\Supply\IarPdfService;
use App\Services\Supply\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class IarController extends Controller
{
    public function __construct(
        private StockService $stock,
        private IarPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('supply.receive');

        $iars = InspectionAcceptanceReport::with(['purchaseOrder:id,po_number', 'inspector:id,name', 'propertyOfficer:id,name'])
            ->latest()
            ->get()
            ->map(fn ($iar) => $this->formatIar($iar));

        return Inertia::render('Supply/IAR/Index', [
            'iars' => $iars,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('supply.receive');

        $purchaseOrders = PurchaseOrder::where('status', 'issued')
            ->orderByDesc('created_at')
            ->get(['id', 'po_number', 'supplier_name'])
            ->map(fn ($po) => ['id' => $po->id, 'label' => "{$po->po_number} — {$po->supplier_name}"]);

        $supplyItems = SupplyItem::where('is_active', true)
            ->with('category:id,name')
            ->orderBy('description')
            ->get(['id', 'item_code', 'description', 'unit', 'estimated_unit_cost', 'category_id']);

        $officers = User::where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Supply/IAR/Create', [
            'purchaseOrders' => $purchaseOrders,
            'supplyItems'    => $supplyItems,
            'officers'       => $officers,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('supply.receive');

        $data = $request->validate([
            'purchase_order_id'   => 'nullable|exists:purchase_orders,id',
            'supplier_name'       => 'required|string|max:255',
            'supplier_address'    => 'nullable|string|max:500',
            'supplier_tin'        => 'nullable|string|max:50',
            'delivery_date'       => 'required|date',
            'inspection_date'     => 'nullable|date',
            'inspector_id'        => 'nullable|exists:users,id',
            'property_officer_id' => 'nullable|exists:users,id',
            'remarks'             => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.supply_item_id'     => 'nullable|exists:supply_items,id',
            'items.*.description'        => 'required|string|max:255',
            'items.*.unit'               => 'required|string|max:50',
            'items.*.quantity_ordered'   => 'required|numeric|min:0.001',
            'items.*.quantity_delivered' => 'required|numeric|min:0',
            'items.*.unit_cost'          => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $request) {
            $iar = InspectionAcceptanceReport::create([
                'iar_number'          => InspectionAcceptanceReport::generateNumber(),
                'purchase_order_id'   => $data['purchase_order_id'] ?? null,
                'supplier_name'       => $data['supplier_name'],
                'supplier_address'    => $data['supplier_address'] ?? null,
                'supplier_tin'        => $data['supplier_tin'] ?? null,
                'delivery_date'       => $data['delivery_date'],
                'inspection_date'     => $data['inspection_date'] ?? null,
                'inspector_id'        => $data['inspector_id'] ?? null,
                'property_officer_id' => $data['property_officer_id'] ?? null,
                'status'              => 'draft',
                'remarks'             => $data['remarks'] ?? null,
                'created_by'          => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                IarItem::create([
                    'iar_id'             => $iar->id,
                    'supply_item_id'     => $item['supply_item_id'] ?? null,
                    'description'        => $item['description'],
                    'unit'               => $item['unit'],
                    'quantity_ordered'   => $item['quantity_ordered'],
                    'quantity_delivered' => $item['quantity_delivered'],
                    'quantity_accepted'  => 0,
                    'quantity_rejected'  => 0,
                    'unit_cost'          => $item['unit_cost'],
                    'is_posted'          => false,
                ]);
            }
        });

        return redirect()->route('supply.iar.index')->with('success', 'IAR created successfully.');
    }

    public function show(InspectionAcceptanceReport $iar)
    {
        $this->authorize('supply.receive');

        $iar->load([
            'purchaseOrder:id,po_number',
            'inspector:id,name',
            'propertyOfficer:id,name',
            'creator:id,name',
            'items.supplyItem:id,item_code,description,unit',
        ]);

        $supplyItems = SupplyItem::where('is_active', true)
            ->orderBy('description')
            ->get(['id', 'item_code', 'description', 'unit', 'estimated_unit_cost']);

        return Inertia::render('Supply/IAR/Show', [
            'iar'        => $this->formatIarDetail($iar),
            'supplyItems' => $supplyItems,
        ]);
    }

    public function inspect(Request $request, InspectionAcceptanceReport $iar)
    {
        $this->authorize('supply.receive');
        abort_unless($iar->isEditable(), 422, 'IAR cannot be edited in its current status.');

        $data = $request->validate([
            'inspection_date' => 'required|date',
            'inspector_id'    => 'required|exists:users,id',
            'items'           => 'required|array',
            'items.*.id'                  => 'required|exists:iar_items,id',
            'items.*.quantity_accepted'   => 'required|numeric|min:0',
            'items.*.quantity_rejected'   => 'required|numeric|min:0',
            'items.*.rejection_reason'    => 'nullable|string',
            'items.*.supply_item_id'      => 'nullable|exists:supply_items,id',
        ]);

        DB::transaction(function () use ($iar, $data) {
            $iar->update([
                'inspection_date' => $data['inspection_date'],
                'inspector_id'    => $data['inspector_id'],
                'status'          => 'inspected',
            ]);

            foreach ($data['items'] as $itemData) {
                IarItem::where('id', $itemData['id'])->where('iar_id', $iar->id)->update([
                    'quantity_accepted' => $itemData['quantity_accepted'],
                    'quantity_rejected' => $itemData['quantity_rejected'],
                    'rejection_reason'  => $itemData['rejection_reason'] ?? null,
                    'supply_item_id'    => $itemData['supply_item_id'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Inspection recorded.');
    }

    public function accept(Request $request, InspectionAcceptanceReport $iar)
    {
        $this->authorize('supply.receive');
        abort_unless($iar->status === 'inspected', 422, 'IAR must be in Inspected status to accept.');

        $data = $request->validate([
            'property_officer_id' => 'required|exists:users,id',
            'remarks'             => 'nullable|string',
        ]);

        DB::transaction(function () use ($iar, $data, $request) {
            $totalAccepted = $iar->items->sum('quantity_accepted');
            $totalDelivered = $iar->items->sum('quantity_delivered');

            $newStatus = $totalAccepted < $totalDelivered ? 'partial' : 'accepted';

            $iar->update([
                'property_officer_id' => $data['property_officer_id'],
                'status'              => $newStatus,
                'remarks'             => $data['remarks'] ?? $iar->remarks,
                'accepted_at'         => now(),
            ]);

            // Post each accepted item to stock card
            $iar->load('items');
            foreach ($iar->items as $item) {
                if ($item->quantity_accepted > 0 && $item->supply_item_id && ! $item->is_posted) {
                    $this->stock->postIar($item, $request->user()->id);
                }
            }
        });

        return back()->with('success', 'IAR accepted and posted to stock.');
    }

    public function printPdf(InspectionAcceptanceReport $iar)
    {
        $this->authorize('supply.receive');

        $iar->load([
            'purchaseOrder:id,po_number',
            'inspector:id,name',
            'propertyOfficer:id,name',
            'creator:id,name',
            'items.supplyItem',
        ]);

        return $this->pdf->generate($iar);
    }

    private function formatIar(InspectionAcceptanceReport $iar): array
    {
        return [
            'id'              => $iar->id,
            'iar_number'      => $iar->iar_number,
            'po_number'       => $iar->purchaseOrder?->po_number,
            'supplier_name'   => $iar->supplier_name,
            'delivery_date'   => $iar->delivery_date?->format('Y-m-d'),
            'inspection_date' => $iar->inspection_date?->format('Y-m-d'),
            'status'          => $iar->status,
            'status_label'    => $iar->statusLabel(),
            'inspector_name'  => $iar->inspector?->name,
            'created_at'      => $iar->created_at->format('Y-m-d'),
        ];
    }

    private function formatIarDetail(InspectionAcceptanceReport $iar): array
    {
        return [
            ...$this->formatIar($iar),
            'purchase_order_id'   => $iar->purchase_order_id,
            'supplier_address'    => $iar->supplier_address,
            'supplier_tin'        => $iar->supplier_tin,
            'inspector_id'        => $iar->inspector_id,
            'property_officer_id' => $iar->property_officer_id,
            'property_officer'    => $iar->propertyOfficer?->name,
            'remarks'             => $iar->remarks,
            'accepted_at'         => $iar->accepted_at?->format('Y-m-d H:i'),
            'created_by_name'     => $iar->creator?->name,
            'is_editable'         => $iar->isEditable(),
            'items'               => $iar->items->map(fn ($item) => [
                'id'                 => $item->id,
                'supply_item_id'     => $item->supply_item_id,
                'item_code'          => $item->supplyItem?->item_code,
                'description'        => $item->description,
                'unit'               => $item->unit,
                'quantity_ordered'   => (float) $item->quantity_ordered,
                'quantity_delivered' => (float) $item->quantity_delivered,
                'quantity_accepted'  => (float) $item->quantity_accepted,
                'quantity_rejected'  => (float) $item->quantity_rejected,
                'unit_cost'          => (float) $item->unit_cost,
                'total_cost'         => $item->totalCost(),
                'rejection_reason'   => $item->rejection_reason,
                'is_posted'          => $item->is_posted,
            ])->values(),
        ];
    }
}
