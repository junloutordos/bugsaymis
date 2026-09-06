<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use Illuminate\Support\Collection;

/**
 * Strategic Function is read-only, identical for every employee, and
 * inherited live from the current fiscal year's OPCR — no snapshot, ever
 * (spec: "true to all employees," campus-wide, not a per-employee commitment).
 */
class StrategicFunctionService
{
    public function currentFiscalYear(): ?int
    {
        return IPCRRatingPeriod::current()->value('year');
    }

    public function currentIndicators(): Collection
    {
        $year = $this->currentFiscalYear();
        if (! $year) {
            return collect();
        }

        return OpcrIndicator::forFiscalYear($year)
            ->with(['agencyOutcome', 'actuals'])
            ->get()
            ->sortBy(fn ($i) => $i->agencyOutcome?->outcome ?? '')
            ->values();
    }
}
