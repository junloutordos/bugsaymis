<?php

namespace App\Http\Controllers\Supply;

use App\Http\Controllers\Controller;
use App\Models\Supply\SupplyItem;
use App\Models\Supply\SupplyLedgerEntry;
use App\Models\Supply\SupplyStockCard;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockCardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('supply.view');

        $items = SupplyItem::where('is_active', true)
            ->with(['category:id,name,type,account_code', 'stockCard'])
            ->orderBy('description')
            ->get()
            ->map(fn ($item) => [
                'id'                => $item->id,
                'item_code'         => $item->item_code,
                'description'       => $item->description,
                'unit'              => $item->unit,
                'category_name'     => $item->category?->name,
                'category_type'     => $item->category?->type,
                'account_code'      => $item->category?->account_code,
                'balance_quantity'  => (float) ($item->stockCard?->balance_quantity ?? 0),
                'average_unit_cost' => (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
                'total_value'       => round(
                    (float) ($item->stockCard?->balance_quantity ?? 0) *
                    (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
                    2
                ),
                'reorder_point'     => $item->reorder_point,
                'is_low_stock'      => $item->isLowStock(),
            ]);

        $summary = [
            'total_items'      => $items->count(),
            'low_stock_count'  => $items->where('is_low_stock', true)->count(),
            'total_value'      => $items->sum('total_value'),
        ];

        return Inertia::render('Supply/StockCard/Index', [
            'items'   => $items->values(),
            'summary' => $summary,
        ]);
    }

    public function show(Request $request, SupplyItem $item)
    {
        $this->authorize('supply.view');

        $item->load(['category:id,name,type,account_code', 'stockCard']);

        $dateFrom = $request->get('date_from');
        $dateTo   = $request->get('date_to');

        $entriesQuery = SupplyLedgerEntry::where('supply_item_id', $item->id)
            ->with('creator:id,name')
            ->orderBy('transaction_date')
            ->orderBy('id');

        if ($dateFrom) {
            $entriesQuery->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $entriesQuery->whereDate('transaction_date', '<=', $dateTo);
        }

        $entries = $entriesQuery->get()->map(fn ($e) => [
            'id'               => $e->id,
            'transaction_date' => $e->transaction_date,
            'reference_type'   => $e->reference_type,
            'reference_number' => $e->reference_number,
            'qty_received'     => (float) $e->qty_received,
            'qty_issued'       => (float) $e->qty_issued,
            'qty_returned'     => (float) $e->qty_returned,
            'balance_after'    => (float) $e->balance_after,
            'unit_cost'        => (float) $e->unit_cost,
            'total_cost'       => (float) $e->total_cost,
            'remarks'          => $e->remarks,
            'created_by'       => $e->creator?->name,
        ]);

        return Inertia::render('Supply/StockCard/Show', [
            'item' => [
                'id'                => $item->id,
                'item_code'         => $item->item_code,
                'description'       => $item->description,
                'unit'              => $item->unit,
                'category_name'     => $item->category?->name,
                'category_type'     => $item->category?->type,
                'account_code'      => $item->category?->account_code,
                'balance_quantity'  => (float) ($item->stockCard?->balance_quantity ?? 0),
                'average_unit_cost' => (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
                'total_value'       => round(
                    (float) ($item->stockCard?->balance_quantity ?? 0) *
                    (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
                    2
                ),
                'reorder_point'     => $item->reorder_point,
                'reorder_quantity'  => $item->reorder_quantity,
                'specifications'    => $item->specifications,
                'is_low_stock'      => $item->isLowStock(),
                'estimated_unit_cost' => (float) $item->estimated_unit_cost,
            ],
            'entries'  => $entries,
            'filters'  => ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }

    public function adjust(Request $request, SupplyItem $item)
    {
        $this->authorize('supply.manage');

        $data = $request->validate([
            'adjustment_qty'  => 'required|numeric',
            'unit_cost'       => 'required|numeric|min:0',
            'reason'          => 'required|string|max:500',
            'transaction_date' => 'required|date',
        ]);

        $card = SupplyStockCard::firstOrCreate(
            ['supply_item_id' => $item->id],
            ['balance_quantity' => 0, 'average_unit_cost' => 0]
        );

        $newBalance = max(0, (float) $card->balance_quantity + $data['adjustment_qty']);
        $card->update(['balance_quantity' => $newBalance]);

        SupplyLedgerEntry::create([
            'supply_item_id'   => $item->id,
            'transaction_date' => $data['transaction_date'],
            'reference_type'   => 'ADJUSTMENT',
            'reference_id'     => null,
            'reference_number' => 'ADJ-' . now()->format('YmdHis'),
            'qty_received'     => $data['adjustment_qty'] > 0 ? $data['adjustment_qty'] : 0,
            'qty_issued'       => $data['adjustment_qty'] < 0 ? abs($data['adjustment_qty']) : 0,
            'qty_returned'     => 0,
            'balance_after'    => $newBalance,
            'unit_cost'        => $data['unit_cost'],
            'total_cost'       => round(abs($data['adjustment_qty']) * $data['unit_cost'], 2),
            'remarks'          => 'Adjustment: ' . $data['reason'],
            'created_by'       => $request->user()->id,
        ]);

        return back()->with('success', 'Stock adjustment recorded.');
    }
}
