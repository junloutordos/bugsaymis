<?php

namespace App\Services\OPCR;

use App\Models\OPCR\OpcrIndicator;
use Illuminate\Support\Collection;

class OpcrPdfRowGrouper
{
    // Priority order for both sorting and rowspan nesting — Program is the
    // outermost boundary (matches the on-screen Program-primary grouping),
    // Pillar/Strategy/Sub-Strategy nest within a Program's own row-range. A
    // higher-priority column changing always resets every column below it,
    // even when a lower column's own value happens to repeat across the
    // boundary (see OpcrPdfRowGrouperTest's boundary-reset case).
    private const COLUMNS = ['program', 'pillar', 'strategy', 'sub_strategy'];

    // Pillar/Strategy/Sub-Strategy come from the many-to-many DOST Strategic
    // Plan tagging (AgencyOutcome::getDost*JoinedAttribute) on the SOURCE
    // Performance Indicator's own outcome node — which may be a specific
    // child under the Program, distinct from its siblings — not from the
    // OpcrIndicator's own agency_outcome_id, which always holds the
    // top-level Program (walked up in OpcrIndicatorPropagationService) and
    // would otherwise collapse every indicator under one Program to the
    // same aggregate tags. Falls back to the Program's own tags only when
    // there's no linked Performance Indicator.
    public function group(Collection $indicators): array
    {
        $dostSource = fn (OpcrIndicator $i) => $i->performanceIndicator?->agencyOutcome ?? $i->agencyOutcome;

        $sorted = $indicators->sortBy(function (OpcrIndicator $i) use ($dostSource) {
            $source = $dostSource($i);

            return [
                $i->agencyOutcome?->outcome ?? '',
                $source?->dost_pillar_names_joined ?? '',
                $source?->dost_strategy_names_joined ?? '',
                $source?->dost_sub_strategy_descriptions_joined ?? '',
                $i->id,
            ];
        })->values();

        $rows = $sorted->map(function (OpcrIndicator $i) use ($dostSource) {
            $source = $dostSource($i);

            return [
                'indicator' => $i,
                'pillar_text' => $source?->dost_pillar_names_joined,
                'strategy_text' => $source?->dost_strategy_names_joined,
                'sub_strategy_text' => $source?->dost_sub_strategy_descriptions_joined,
                'key' => [
                    'program' => $i->agencyOutcome?->outcome,
                    'pillar' => $source?->dost_pillar_names_joined,
                    'strategy' => $source?->dost_strategy_names_joined,
                    'sub_strategy' => $source?->dost_sub_strategy_descriptions_joined,
                ],
                'rowspan' => array_fill_keys(self::COLUMNS, 1),
                'show' => array_fill_keys(self::COLUMNS, true),
            ];
        })->all();

        $count = count($rows);
        $isNewGroup = [];
        for ($i = 0; $i < $count; $i++) {
            foreach (self::COLUMNS as $colIndex => $col) {
                if ($i === 0) {
                    $isNewGroup[$i][$col] = true;

                    continue;
                }

                $higherBoundary = false;
                for ($h = 0; $h < $colIndex; $h++) {
                    if ($isNewGroup[$i][self::COLUMNS[$h]]) {
                        $higherBoundary = true;

                        break;
                    }
                }

                $isNewGroup[$i][$col] = $higherBoundary || $rows[$i]['key'][$col] !== $rows[$i - 1]['key'][$col];
            }
        }

        foreach (self::COLUMNS as $col) {
            $i = 0;
            while ($i < $count) {
                $span = 1;
                $j = $i + 1;
                while ($j < $count && ! $isNewGroup[$j][$col]) {
                    $span++;
                    $j++;
                }
                $rows[$i]['rowspan'][$col] = $span;
                for ($k = $i + 1; $k < $j; $k++) {
                    $rows[$k]['show'][$col] = false;
                }
                $i = $j;
            }
        }

        return $rows;
    }
}
