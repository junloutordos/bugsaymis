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

        $indicators = OpcrIndicator::forFiscalYear($year)
            ->with([
                'agencyOutcome.dostStrategies.pillar',
                'agencyOutcome.dostStrategies.subStrategies',
                'agencyOutcome.parent.dostStrategies.pillar',
                'agencyOutcome.parent.dostStrategies.subStrategies',
                'performanceIndicator.agencyOutcome.dostStrategies.pillar',
                'performanceIndicator.agencyOutcome.dostStrategies.subStrategies',
                'performanceIndicator.agencyOutcome.parent.dostStrategies.pillar',
                'performanceIndicator.agencyOutcome.parent.dostStrategies.subStrategies',
                'actuals',
            ])
            ->get()
            ->sortBy(fn ($i) => $i->agencyOutcome?->outcome ?? '')
            ->values();

        $this->attachRowspans($indicators);

        return $indicators;
    }

    /**
     * Attaches strategy_rowspan / sub_strategy_rowspan / program_rowspan to each
     * indicator so the Strategy, Sub Strategy, and PSHS Program cells can be
     * merged (screen and print) without re-sorting the list — sort order stays
     * Program-first, per spec.
     *
     * Strategy and Sub Strategy are computed as a genuine nested pair (a Sub
     * Strategy belongs to exactly one Strategy): a Strategy change always
     * restarts Sub Strategy too, even if the sub-strategy text happens to
     * coincide (e.g. both blank), because two different Strategies must never
     * visually share one merged Sub Strategy cell.
     *
     * Program is independent of both — it's a separate, cross-cutting
     * classification, not a child of Strategy/Sub Strategy. It resolves
     * through the indicator's own (coarser) Agency Outcome, while Strategy/Sub
     * Strategy resolve through the more granular linked Performance
     * Indicator's own Agency Outcome — the two can legitimately vary
     * independently of each other row-to-row.
     */
    private function attachRowspans(Collection $indicators): void
    {
        $keys = $indicators->map(fn ($i) => [
            'strategy' => $this->dostSource($i)?->dost_strategy_names_joined ?? '',
            'sub_strategy' => $this->dostSource($i)?->dost_sub_strategy_descriptions_joined ?? '',
            'program' => $i->agencyOutcome?->outcome ?? '',
        ])->values();

        $count = $keys->count();
        $starts = [];
        $prev = null;

        for ($i = 0; $i < $count; $i++) {
            $newStrategy = $prev === null || $keys[$i]['strategy'] !== $prev['strategy'];
            $newSubStrategy = $newStrategy || $keys[$i]['sub_strategy'] !== $prev['sub_strategy'];
            $newProgram = $prev === null || $keys[$i]['program'] !== $prev['program'];

            $starts[$i] = ['strategy' => $newStrategy, 'sub_strategy' => $newSubStrategy, 'program' => $newProgram];
            $prev = $keys[$i];
        }

        foreach (['strategy', 'sub_strategy', 'program'] as $level) {
            for ($i = 0; $i < $count; $i++) {
                if (! $starts[$i][$level]) {
                    $indicators[$i]->setAttribute("{$level}_rowspan", 0);

                    continue;
                }

                $span = 1;
                for ($j = $i + 1; $j < $count && ! $starts[$j][$level]; $j++) {
                    $span++;
                }
                $indicators[$i]->setAttribute("{$level}_rowspan", $span);
            }
        }
    }

    private function dostSource(OpcrIndicator $indicator): ?\App\Models\AgencyOutcome
    {
        return $indicator->performanceIndicator?->agencyOutcome ?? $indicator->agencyOutcome;
    }
}
