<?php

namespace App\Services\PPMP;

use App\Models\Division;
use App\Models\PPMP\Ppmp;
use Illuminate\Support\Facades\DB;

class PPMPDashboardService
{
    public function __construct(private CostComputationService $costService)
    {
    }

    public function getMetrics(int $fiscalYear): array
    {
        return [
            'status_counts'     => $this->getStatusCounts($fiscalYear),
            'budget_by_category' => $this->costService->computeAPPTotals($fiscalYear),
            'submission_rate'   => $this->getSubmissionRate($fiscalYear),
            'unit_compliance'   => $this->getUnitCompliance($fiscalYear),
        ];
    }

    private function getStatusCounts(int $fiscalYear): array
    {
        $counts = Ppmp::where('fiscal_year', $fiscalYear)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        // Ensure all statuses are present
        $result = [];
        foreach (Ppmp::STATUSES as $status) {
            $result[$status] = $counts[$status] ?? 0;
        }

        return $result;
    }

    private function getSubmissionRate(int $fiscalYear): array
    {
        $totalUnits = Division::where('status', 'active')->count();

        $submittedUnits = Ppmp::where('fiscal_year', $fiscalYear)
            ->whereIn('status', [
                Ppmp::STATUS_SUBMITTED,
                Ppmp::STATUS_APPROVED,
                Ppmp::STATUS_CONSOLIDATED,
            ])
            ->distinct('division_id')
            ->count('division_id');

        return [
            'total_units'    => $totalUnits,
            'submitted_units' => $submittedUnits,
            'rate_percent'   => $totalUnits > 0 ? round(($submittedUnits / $totalUnits) * 100, 1) : 0,
        ];
    }

    private function getUnitCompliance(int $fiscalYear): array
    {
        $divisions = Division::where('status', 'active')
            ->orderBy('division_name')
            ->get(['id', 'division_name', 'acronym']);

        $ppmps = Ppmp::where('fiscal_year', $fiscalYear)
            ->get(['id', 'division_id', 'status', 'submitted_at'])
            ->keyBy('division_id');

        return $divisions->map(function ($div) use ($ppmps, $fiscalYear) {
            $ppmp = $ppmps->get($div->id);

            return [
                'division_id'   => $div->id,
                'division_name' => $div->division_name,
                'acronym'       => $div->acronym,
                'ppmp_status'   => $ppmp?->status,
                'total_budget'  => $ppmp
                    ? (float) $ppmp->items()->sum('total_cost')
                    : 0,
                'submitted_at'  => $ppmp?->submitted_at?->format('M d, Y'),
            ];
        })->all();
    }
}
