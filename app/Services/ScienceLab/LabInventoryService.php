<?php

namespace App\Services\ScienceLab;

use App\Models\ScienceLab\LabConsumable;
use App\Models\ScienceLab\LabConsumableLot;
use App\Models\ScienceLab\LabStockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stock management for laboratory consumables & reagents.
 * Maintains the append-only ledger (lab_stock_movements) with a running
 * balance and enforces FIFO / FEFO issuance across lots (CIM 4.4 §3.1.8–3.1.10).
 */
class LabInventoryService
{
    /** Current on-hand balance for a consumable (sum of available lots). */
    public function balance(int $consumableId): float
    {
        return (float) LabConsumableLot::where('lab_consumable_id', $consumableId)
            ->where('status', 'available')
            ->sum('quantity');
    }

    /**
     * Receive stock into a new lot and record a RECEIVE ledger entry.
     */
    public function receive(LabConsumable $consumable, array $data, ?int $userId): LabConsumableLot
    {
        return DB::transaction(function () use ($consumable, $data, $userId) {
            $lot = LabConsumableLot::create([
                'lab_consumable_id' => $consumable->id,
                'lot_no'            => $data['lot_no'] ?? null,
                'quantity'          => $data['quantity'],
                'received_date'     => $data['received_date'] ?? now()->toDateString(),
                'expiry_date'       => $data['expiry_date'] ?? null,
                'unit_cost'         => $data['unit_cost'] ?? 0,
                'status'            => 'available',
                'remarks'           => $data['remarks'] ?? null,
            ]);

            $this->log($consumable->id, $lot->id, [
                'reference_type'  => 'RECEIVE',
                'qty_received'    => $data['quantity'],
                'unit_cost'       => $data['unit_cost'] ?? 0,
                'reference_number'=> $data['reference_number'] ?? null,
                'transaction_date'=> $lot->received_date,
                'remarks'         => $data['remarks'] ?? null,
            ], $userId);

            return $lot;
        });
    }

    /**
     * Issue a quantity of a consumable, drawing from lots FEFO (if expiry-tracked)
     * else FIFO. Returns the lot breakdown consumed. Throws if insufficient stock.
     */
    public function issue(LabConsumable $consumable, float $quantity, array $meta, ?int $userId): array
    {
        return DB::transaction(function () use ($consumable, $quantity, $meta, $userId) {
            $available = $this->balance($consumable->id);
            if ($quantity > $available + 1e-9) {
                throw new \RuntimeException("Insufficient stock for {$consumable->name}. On hand: {$available}, requested: {$quantity}.");
            }

            $orderCol = $consumable->tracks_expiry ? 'expiry_date' : 'received_date';
            $lots = LabConsumableLot::where('lab_consumable_id', $consumable->id)
                ->where('status', 'available')
                ->where('quantity', '>', 0)
                ->orderByRaw("{$orderCol} IS NULL")   // dated lots first
                ->orderBy($orderCol)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remaining = $quantity;
            $consumed  = [];

            foreach ($lots as $lot) {
                if ($remaining <= 1e-9) {
                    break;
                }
                $take = min((float) $lot->quantity, $remaining);
                $lot->quantity = (float) $lot->quantity - $take;
                if ($lot->quantity <= 1e-9) {
                    $lot->quantity = 0;
                    $lot->status = 'depleted';
                }
                $lot->save();
                $remaining -= $take;

                $this->log($consumable->id, $lot->id, [
                    'reference_type'   => 'ISSUE',
                    'qty_issued'       => $take,
                    'unit_cost'        => $lot->unit_cost,
                    'reference_type_id'=> $meta['reference_id'] ?? null,
                    'reference_id'     => $meta['reference_id'] ?? null,
                    'reference_number' => $meta['reference_number'] ?? null,
                    'transaction_date' => $meta['transaction_date'] ?? now()->toDateString(),
                    'remarks'          => $meta['remarks'] ?? null,
                ], $userId);

                $consumed[] = ['lot_id' => $lot->id, 'lot_no' => $lot->lot_no, 'qty' => $take];
            }

            return $consumed;
        });
    }

    /** Adjustment (positive or negative) with a reason. Writes a new balancing lot when positive. */
    public function adjust(LabConsumable $consumable, float $delta, string $reason, ?int $userId): void
    {
        DB::transaction(function () use ($consumable, $delta, $reason, $userId) {
            if ($delta >= 0) {
                $lot = LabConsumableLot::create([
                    'lab_consumable_id' => $consumable->id,
                    'lot_no'            => 'ADJ-' . now()->format('YmdHis'),
                    'quantity'          => $delta,
                    'received_date'     => now()->toDateString(),
                    'status'            => 'available',
                    'remarks'           => $reason,
                ]);
                $this->log($consumable->id, $lot->id, [
                    'reference_type'   => 'ADJUSTMENT',
                    'qty_received'     => $delta,
                    'transaction_date' => now()->toDateString(),
                    'remarks'          => $reason,
                ], $userId);
            } else {
                $this->issue($consumable, abs($delta), [
                    'reference_number' => 'ADJUSTMENT',
                    'remarks'          => $reason,
                ], $userId);
            }
        });
    }

    /** Mark a lot disposed (expired/damaged) and record a DISPOSAL entry. */
    public function disposeLot(LabConsumableLot $lot, string $reason, ?int $userId): void
    {
        DB::transaction(function () use ($lot, $reason, $userId) {
            $qty = (float) $lot->quantity;
            $lot->update(['quantity' => 0, 'status' => 'disposed', 'remarks' => trim(($lot->remarks ? $lot->remarks . ' | ' : '') . $reason)]);
            $this->log($lot->lab_consumable_id, $lot->id, [
                'reference_type'   => 'DISPOSAL',
                'qty_issued'       => $qty,
                'transaction_date' => now()->toDateString(),
                'remarks'          => $reason,
            ], $userId);
        });
    }

    /** Flag lots whose expiry date has passed (§3.1.10 segregation). */
    public function markExpiredLots(): int
    {
        return LabConsumableLot::where('status', 'available')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', Carbon::today())
            ->update(['status' => 'expired']);
    }

    /** Consumables at or below their reorder level. */
    public function lowStock()
    {
        return LabConsumable::where('status', 'active')
            ->get()
            ->filter(fn ($c) => $c->reorder_level > 0 && $this->balance($c->id) <= (float) $c->reorder_level)
            ->values();
    }

    /** Lots expiring within the given number of days. */
    public function expiringSoon(int $days = 30)
    {
        return LabConsumableLot::with('consumable')
            ->where('status', 'available')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', Carbon::today())
            ->whereDate('expiry_date', '<=', Carbon::today()->addDays($days))
            ->orderBy('expiry_date')
            ->get();
    }

    /** Append a ledger row and compute the running balance. */
    private function log(int $consumableId, ?int $lotId, array $data, ?int $userId): LabStockMovement
    {
        $balance = $this->balance($consumableId);

        return LabStockMovement::create([
            'lab_consumable_id' => $consumableId,
            'lot_id'            => $lotId,
            'transaction_date'  => $data['transaction_date'] ?? now()->toDateString(),
            'reference_type'    => $data['reference_type'],
            'reference_id'      => $data['reference_id'] ?? null,
            'reference_number'  => $data['reference_number'] ?? null,
            'qty_received'      => $data['qty_received'] ?? 0,
            'qty_issued'        => $data['qty_issued'] ?? 0,
            'qty_returned'      => $data['qty_returned'] ?? 0,
            'balance_after'     => $balance,
            'unit_cost'         => $data['unit_cost'] ?? 0,
            'remarks'           => $data['remarks'] ?? null,
            'created_by'        => $userId,
        ]);
    }
}
