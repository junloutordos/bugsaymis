<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use Illuminate\Support\Collection;

/**
 * Strategic Function is read-only, identical for every employee, and
 * inherited from the PRIOR fiscal year's OPCR — a year's own OPCR rating
 * isn't knowable until that year is essentially over, so an IPCR for
 * fiscal year N reads fiscal year N-1's OPCR indicators. No snapshot,
 * ever (spec: "true to all employees," campus-wide, not a per-employee
 * commitment).
 */
class StrategicFunctionService
{
    public function ratingFiscalYear(): ?int
    {
        $currentYear = IPCRRatingPeriod::current()->value('year');

        return $currentYear ? $currentYear - 1 : null;
    }

    public function currentIndicators(): Collection
    {
        $year = $this->ratingFiscalYear();
        if (! $year) {
            return collect();
        }

        return OpcrIndicator::forFiscalYear($year)
            ->with([
                'agencyOutcome',
                'performanceIndicator.agencyOutcome.dostStrategies.pillar',
                'performanceIndicator.agencyOutcome.dostStrategies.subStrategies',
                'performanceIndicator.agencyOutcome.parent.dostStrategies.pillar',
                'performanceIndicator.agencyOutcome.parent.dostStrategies.subStrategies',
                'actuals',
            ])
            ->get()
            ->sortBy(fn ($i) => $i->agencyOutcome?->outcome ?? '')
            ->values();
    }
}
