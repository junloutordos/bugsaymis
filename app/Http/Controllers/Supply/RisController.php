<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Supply\RequisitionIssueSlip;
use App\Models\Supply\RisItem;
use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyStockCard;
use App\Models\User;
use App\Services\Supply\RisPdfService;
use App\Services\Supply\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RisController extends Controller
{
    public function __construct(
        private StockService $stock,
        private RisPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('supply.issue');

        $user = $request->user();

        $query = RequisitionIssueSlip::with([
            'division:id,division_name',
            'requester:id,name',
            'approver:id,name',
        ])->latest();

        // Non-supply-manage users can only see their own division's RISes
        if (! $user->isSuperAdmin() && ! $user->hasAnyPermission(['supply.manage', 'supply.issue'])) {
            $query->where(function ($q) use ($user) {
                $q->where('requested_by', $user->id)
                    ->orWhere('division_id', $user->division_id);
            });
        }

        return Inertia::render('Supply/RIS/Index', [
            'ris_list' => $query->get()->map(fn ($ris) => $this->formatRis($ris)),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('supply.view');

        $supplyItems = SupplyItem::where('is_active', true)
            ->with(['category:id,name', 'stockCard'])
            ->orderBy('description')
            ->get()
            ->map(fn ($i) => [
                'id'               => $i->id,
                'item_code'        => $i->item_code,
                'description'      => $i->description,
                'unit'             => $i->unit,
                'balance_quantity' => (float) ($i->stockCard?->balance_quantity ?? 0),
                'average_unit_cost' => (float) ($i->stockCard?->average_unit_cost ?? $i->estimated_unit_cost),
            ]);

        $divisions = Division::where('status', 'active')
            ->orderBy('division_name')
            ->get(['id', 'division_name']);

        return Inertia::render('Supply/RIS/Create', [
            'supplyItems' => $supplyItems,
            'divisions'   => $divisions,
            'userDivision' => $request->user()->division_id,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('supply.view');

        $data = $request->validate([
            'division_id'     => 'nullable|exists:divisions,id',
            'purpose'         => 'required|string|max:500',
            'date_requested'  => 'required|date',
            'date_needed'     => 'nullable|date|after_or_equal:date_requested',
            'remarks'         => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.supply_item_id'      => 'required|exists:supply_items,id',
            'items.*.quantity_requested'  => 'required|numeric|min:0.001',
            'items.*.remarks'             => 'nullable|string',
        ]);

        DB::transaction(function () use ($data, $request) {
            $ris = RequisitionIssueSlip::create([
                'ris_number'     => RequisitionIssueSlip::generateNumber(),
                'division_id'    => $data['division_id'] ?? $request->user()->division_id,
                'purpose'        => $data['purpose'],
                'date_requested' => $data['date_requested'],
                'date_needed'    => $data['date_needed'] ?? null,
                'requested_by'   => $request->user()->id,
                'status'         => 'draft',
                'remarks'        => $data['remarks'] ?? null,
                'created_by'     => $request->user()->id,
            ]);

            foreach ($data['items'] as $item) {
                RisItem::create([
                    'ris_id'             => $ris->id,
                    'supply_item_id'     => $item['supply_item_id'],
                    'quantity_requested' => $item['quantity_requested'],
                    'quantity_issued'    => 0,
                    'unit_cost'          => 0,
                    'remarks'            => $item['remarks'] ?? null,
                ]);
            }
        });

        return redirect()->route('supply.ris.index')->with('success', 'RIS created successfully.');
    }

    public function show(RequisitionIssueSlip $ris)
    {
        $this->authorize('supply.view');

        $ris->load([
            'division:id,division_name',
            'requester:id,name',
            'approver:id,name',
            'issuer:id,name',
            'creator:id,name',
            'items.supplyItem:id,item_code,description,unit',
        ]);

        return Inertia::render('Supply/RIS/Show', [
            'ris' => $this->formatRisDetail($ris),
        ]);
    }

    public function submit(RequisitionIssueSlip $ris)
    {
        $this->authorize('supply.view');
        abort_unless($ris->isEditable(), 422, 'Only draft RISes can be submitted.');

        $ris->update(['status' => 'pending']);

        return back()->with('success', 'RIS submitted for approval.');
    }

    public function approve(Request $request, RequisitionIssueSlip $ris)
    {
        $this->authorize('supply.manage');
        abort_unless($ris->status === 'pending', 422, 'RIS must be pending to approve.');

        $data = $request->validate([
            'approval_remarks' => 'nullable|string',
        ]);

        $ris->update([
            'status'           => 'approved',
            'approved_by'      => $request->user()->id,
            'approved_at'      => now(),
            'approval_remarks' => $data['approval_remarks'] ?? null,
        ]);

        return back()->with('success', 'RIS approved.');
    }

    public function reject(Request $request, RequisitionIssueSlip $ris)
    {
        $this->authorize('supply.manage');
        abort_unless(in_array($ris->status, ['pending', 'approved']), 422, 'RIS cannot be rejected at this stage.');

        $data = $request->validate([
            'approval_remarks' => 'required|string|max:500',
        ]);

        $ris->update([
            'status'           => 'cancelled',
            'approval_remarks' => $data['approval_remarks'],
        ]);

        return back()->with('success', 'RIS cancelled.');
    }

    public function issue(Request $request, RequisitionIssueSlip $ris)
    {
        $this->authorize('supply.issue');
        abort_unless($ris->canBeIssued(), 422, 'RIS is not in an issuable status.');

        $data = $request->validate([
            'items'           => 'required|array|min:1',
            'items.*.id'      => 'required|exists:ris_items,id',
            'items.*.qty'     => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($ris, $data, $request) {
            foreach ($data['items'] as $itemData) {
                if ($itemData['qty'] <= 0) {
                    continue;
                }

                $risItem = RisItem::where('id', $itemData['id'])
                    ->where('ris_id', $ris->id)
                    ->firstOrFail();

                $remaining = (float) $risItem->quantity_requested - (float) $risItem->quantity_issued;
                $qtyToIssue = min($itemData['qty'], $remaining);

                if ($qtyToIssue > 0) {
                    $this->stock->issueRis($risItem, $qtyToIssue, $request->user()->id);
                }
            }

            // Refresh items to determine new status
            $ris->load('items');
            $allFull = $ris->items->every(fn ($i) => $i->isFullyIssued());
            $anyIssued = $ris->items->some(fn ($i) => (float) $i->quantity_issued > 0);

            $newStatus = $allFull ? 'fully_issued' : ($anyIssued ? 'partially_issued' : $ris->status);

            $ris->update([
                'status'    => $newStatus,
                'issued_by' => $request->user()->id,
                'issued_at' => now(),
            ]);
        });

        return back()->with('success', 'Items issued successfully.');
    }

    public function printPdf(RequisitionIssueSlip $ris)
    {
        $this->authorize('supply.view');

        $ris->load([
            'division:id,division_name',
            'requester:id,name',
            'approver:id,name',
            'issuer:id,name',
            'creator:id,name',
            'items.supplyItem',
        ]);

        return $this->pdf->generate($ris);
    }

    private function formatRis(RequisitionIssueSlip $ris): array
    {
        return [
            'id'             => $ris->id,
            'ris_number'     => $ris->ris_number,
            'division_name'  => $ris->division?->division_name,
            'purpose'        => $ris->purpose,
            'date_requested' => $ris->date_requested?->format('Y-m-d'),
            'date_needed'    => $ris->date_needed?->format('Y-m-d'),
            'requested_by'   => $ris->requester?->name,
            'approved_by'    => $ris->approver?->name,
            'status'         => $ris->status,
            'status_label'   => $ris->statusLabel(),
            'created_at'     => $ris->created_at->format('Y-m-d'),
        ];
    }

    private function formatRisDetail(RequisitionIssueSlip $ris): array
    {
        return [
            ...$this->formatRis($ris),
            'division_id'      => $ris->division_id,
            'remarks'          => $ris->remarks,
            'approved_at'      => $ris->approved_at?->format('Y-m-d H:i'),
            'approval_remarks' => $ris->approval_remarks,
            'issued_by'        => $ris->issuer?->name,
            'issued_at'        => $ris->issued_at?->format('Y-m-d H:i'),
            'created_by_name'  => $ris->creator?->name,
            'is_editable'      => $ris->isEditable(),
            'can_be_issued'    => $ris->canBeIssued(),
            'items'            => $ris->items->map(fn ($item) => [
                'id'                 => $item->id,
                'supply_item_id'     => $item->supply_item_id,
                'item_code'          => $item->supplyItem?->item_code,
                'description'        => $item->supplyItem?->description ?? '—',
                'unit'               => $item->supplyItem?->unit ?? $item->ris->items->firstWhere('id', $item->id)?->unit ?? '',
                'quantity_requested' => (float) $item->quantity_requested,
                'quantity_issued'    => (float) $item->quantity_issued,
                'unit_cost'          => (float) $item->unit_cost,
                'total_cost'         => $item->totalCost(),
                'remarks'            => $item->remarks,
                'is_fully_issued'    => $item->isFullyIssued(),
            ])->values(),
        ];
    }
}
