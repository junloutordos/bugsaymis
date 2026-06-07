<?php

namespace App\Services\Supply;

use App\Models\Supply\IarItem;
use App\Models\Supply\RisItem;
use App\Models\Supply\SupplyLedgerEntry;
use App\Models\Supply\SupplyStockCard;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Post accepted IAR items to the stock card (moving average cost method).
     */
    public function postIar(IarItem $item, int $userId): void
    {
        if ($item->is_posted || ! $item->supply_item_id || $item->quantity_accepted <= 0) {
            return;
        }

        DB::transaction(function () use ($item, $userId) {
            $card = SupplyStockCard::firstOrCreate(
                ['supply_item_id' => $item->supply_item_id],
                ['balance_quantity' => 0, 'average_unit_cost' => 0]
            );

            $oldQty  = (float) $card->balance_quantity;
            $oldCost = (float) $card->average_unit_cost;
            $newQty  = (float) $item->quantity_accepted;
            $newCost = (float) $item->unit_cost;

            $totalQty  = $oldQty + $newQty;
            $newAvgCost = $totalQty > 0
                ? (($oldQty * $oldCost) + ($newQty * $newCost)) / $totalQty
                : $newCost;

            $card->update([
                'balance_quantity'  => $totalQty,
                'average_unit_cost' => round($newAvgCost, 4),
            ]);

            SupplyLedgerEntry::create([
                'supply_item_id'   => $item->supply_item_id,
                'transaction_date' => $item->iar->delivery_date ?? today(),
                'reference_type'   => 'IAR',
                'reference_id'     => $item->iar_id,
                'reference_number' => $item->iar->iar_number,
                'qty_received'     => $newQty,
                'qty_issued'       => 0,
                'qty_returned'     => 0,
                'balance_after'    => $totalQty,
                'unit_cost'        => $newCost,
                'total_cost'       => round($newQty * $newCost, 2),
                'remarks'          => 'IAR acceptance posted',
                'created_by'       => $userId,
            ]);

            $item->update(['is_posted' => true]);
        });
    }

    /**
     * Issue items for an RIS line — deduct from stock (weighted average cost).
     */
    public function issueRis(RisItem $risItem, float $qtyToIssue, int $userId): void
    {
        abort_if($qtyToIssue <= 0, 422, 'Quantity must be positive.');

        $card = SupplyStockCard::where('supply_item_id', $risItem->supply_item_id)->first();
        abort_if(! $card || $card->balance_quantity < $qtyToIssue, 422,
            "Insufficient stock for item #{$risItem->supply_item_id}. Available: " . ($card?->balance_quantity ?? 0));

        DB::transaction(function () use ($risItem, $qtyToIssue, $userId, $card) {
            $avgCost   = (float) $card->average_unit_cost;
            $newBalance = (float) $card->balance_quantity - $qtyToIssue;

            $card->update(['balance_quantity' => $newBalance]);

            $risItem->update([
                'quantity_issued' => (float) $risItem->quantity_issued + $qtyToIssue,
                'unit_cost'       => $avgCost,
            ]);

            SupplyLedgerEntry::create([
                'supply_item_id'   => $risItem->supply_item_id,
                'transaction_date' => $risItem->ris->issued_at?->toDateString() ?? today()->toDateString(),
                'reference_type'   => 'RIS',
                'reference_id'     => $risItem->ris_id,
                'reference_number' => $risItem->ris->ris_number,
                'qty_received'     => 0,
                'qty_issued'       => $qtyToIssue,
                'qty_returned'     => 0,
                'balance_after'    => $newBalance,
                'unit_cost'        => $avgCost,
                'total_cost'       => round($qtyToIssue * $avgCost, 2),
                'remarks'          => 'Issued via RIS',
                'created_by'       => $userId,
            ]);
        });
    }

    /**
     * Return unused supplies back to stock.
     */
    public function returnStock(int $supplyItemId, float $qty, float $unitCost, string $reference, int $referenceId, int $userId): void
    {
        abort_if($qty <= 0, 422, 'Quantity must be positive.');

        DB::transaction(function () use ($supplyItemId, $qty, $unitCost, $reference, $referenceId, $userId) {
            $card = SupplyStockCard::firstOrCreate(
                ['supply_item_id' => $supplyItemId],
                ['balance_quantity' => 0, 'average_unit_cost' => 0]
            );

            $newBalance = (float) $card->balance_quantity + $qty;
            $card->update(['balance_quantity' => $newBalance]);

            SupplyLedgerEntry::create([
                'supply_item_id'   => $supplyItemId,
                'transaction_date' => today(),
                'reference_type'   => 'RETURN',
                'reference_id'     => $referenceId,
                'reference_number' => $reference,
                'qty_received'     => 0,
                'qty_issued'       => 0,
                'qty_returned'     => $qty,
                'balance_after'    => $newBalance,
                'unit_cost'        => $unitCost,
                'total_cost'       => round($qty * $unitCost, 2),
                'remarks'          => 'Return to stockroom',
                'created_by'       => $userId,
            ]);
        });
    }
}
