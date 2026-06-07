<?php

namespace App\Services\Property;

use App\Models\Property\PropertyItem;
use Carbon\Carbon;

class DepreciationService
{
    /**
     * Build the full lapsing schedule for a property item.
     * Returns an array of annual rows (year, annual_depreciation, accumulated, book_value).
     */
    public function lapsingSchedule(PropertyItem $item): array
    {
        $cost           = $item->totalCost();
        $residual       = $item->residualValue();
        $depreciable    = $cost - $residual;
        $usefulLife     = $item->category?->usefulLifeMonths() ?? 60;
        $monthly        = $usefulLife > 0 ? round($depreciable / $usefulLife, 4) : 0;

        $startDate = Carbon::parse($item->acquisition_date)->startOfMonth()->addMonth();
        $endDate   = $startDate->copy()->addMonths($usefulLife - 1)->endOfMonth();

        $rows             = [];
        $accumulated      = 0;
        $currentYear      = (int) $startDate->format('Y');
        $yearDepreciation = 0;
        $monthCount       = 0;

        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $monthDepreciation = $monthly;
            $accumulated       = min($depreciable, round($accumulated + $monthDepreciation, 2));
            $yearDepreciation  = round($yearDepreciation + $monthDepreciation, 2);
            $monthCount++;

            $isLastMonth = $cursor->gte($endDate) || $monthCount >= $usefulLife;

            if ($cursor->format('m') == 12 || $isLastMonth) {
                $rows[] = [
                    'year'                => $currentYear,
                    'annual_depreciation' => round($yearDepreciation, 2),
                    'accumulated'         => $accumulated,
                    'book_value'          => max($residual, round($cost - $accumulated, 2)),
                ];
                $currentYear++;
                $yearDepreciation = 0;
            }

            if ($isLastMonth) break;
            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * Accumulated depreciation and book value as of a given date.
     */
    public function asOf(PropertyItem $item, Carbon $asOf): array
    {
        $accumulated = $item->accumulatedDepreciation($asOf);
        return [
            'cost'          => $item->totalCost(),
            'residual'      => $item->residualValue(),
            'accumulated'   => $accumulated,
            'book_value'    => $item->bookValue($asOf),
            'monthly_rate'  => $item->monthlyDepreciation(),
        ];
    }

    /**
     * Build the RPCI data: current inventory values grouped by category.
     */
    public function rpciData(): array
    {
        return \App\Models\Supply\SupplyItem::where('is_active', true)
            ->with(['category:id,name,account_code,type', 'stockCard'])
            ->orderBy('description')
            ->get()
            ->map(fn ($item) => [
                'item_code'         => $item->item_code,
                'description'       => $item->description,
                'unit'              => $item->unit,
                'category_name'     => $item->category?->name,
                'account_code'      => $item->category?->account_code,
                'balance_quantity'  => (float) ($item->stockCard?->balance_quantity ?? 0),
                'unit_cost'         => (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
                'total_value'       => round(
                    (float) ($item->stockCard?->balance_quantity ?? 0) *
                    (float) ($item->stockCard?->average_unit_cost ?? $item->estimated_unit_cost),
                    2
                ),
            ])
            ->toArray();
    }

    /**
     * Build RPCPPE data: all serviceable property items with book value as of today.
     */
    public function rpcppeData(): array
    {
        return PropertyItem::where('status', 'serviceable')
            ->with(['category:id,name,account_code', 'currentOfficer:id,name'])
            ->orderBy('property_number')
            ->get()
            ->map(fn ($item) => [
                'property_number'   => $item->property_number,
                'description'       => $item->description,
                'unit'              => $item->unit,
                'quantity'          => (float) $item->quantity,
                'unit_cost'         => (float) $item->unit_cost,
                'total_cost'        => $item->totalCost(),
                'acquisition_date'  => $item->acquisition_date?->format('Y-m-d'),
                'category_name'     => $item->category?->name,
                'account_code'      => $item->category?->account_code,
                'officer_name'      => $item->currentOfficer?->name,
                'book_value'        => $item->bookValue(),
                'accumulated_dep'   => $item->accumulatedDepreciation(),
                'monthly_dep'       => $item->monthlyDepreciation(),
                'location'          => $item->location,
                'serial_number'     => $item->serial_number,
                'brand'             => $item->brand,
                'model'             => $item->model,
                'status'            => $item->status,
            ])
            ->toArray();
    }
}
