<?php

namespace App\Services\PPMP;

use App\Models\PPMP\PpmpItem;
use Illuminate\Support\Facades\DB;

class CostComputationService
{
    /**
     * Get per-category summary for a single PPMP.
     */
    public function computePPMPSummary(int $ppmpId): array
    {
        return PpmpItem::where('ppmp_id', $ppmpId)
            ->select('category', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(total_cost) as subtotal'))
            ->groupBy('category')
            ->orderByRaw("FIELD(category, 'goods', 'infrastructure', 'consulting_services')")
            ->get()
            ->map(fn ($row) => [
                'category'   => $row->category,
                'label'      => PpmpItem::CATEGORY_LABELS[$row->category] ?? $row->category,
                'item_count' => (int) $row->item_count,
                'subtotal'   => (float) $row->subtotal,
            ])
            ->all();
    }

    /**
     * Grand total for a single PPMP.
     */
    public function computePPMPGrandTotal(int $ppmpId): float
    {
        return (float) PpmpItem::where('ppmp_id', $ppmpId)->sum('total_cost');
    }

    /**
     * Aggregated totals across all approved/consolidated PPMPs for a fiscal year.
     */
    public function computeAPPTotals(int $fiscalYear): array
    {
        $rows = DB::table('ppmp_items as pi')
            ->join('ppmp as p', 'pi.ppmp_id', '=', 'p.id')
            ->where('p.fiscal_year', $fiscalYear)
            ->whereIn('p.status', ['approved', 'consolidated'])
            ->select('pi.category', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(pi.total_cost) as subtotal'))
            ->groupBy('pi.category')
            ->orderByRaw("FIELD(pi.category, 'goods', 'infrastructure', 'consulting_services')")
            ->get();

        $categories = [];
        $grandTotal = 0;

        foreach ($rows as $row) {
            $categories[] = [
                'category'   => $row->category,
                'label'      => PpmpItem::CATEGORY_LABELS[$row->category] ?? $row->category,
                'item_count' => (int) $row->item_count,
                'subtotal'   => (float) $row->subtotal,
            ];
            $grandTotal += (float) $row->subtotal;
        }

        return [
            'categories'  => $categories,
            'grand_total' => $grandTotal,
        ];
    }
}
