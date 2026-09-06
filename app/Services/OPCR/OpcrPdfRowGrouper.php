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

    // Pillar/Strategy/Sub-Strategy come from the Program's own many-to-many
    // DOST Strategic Plan tagging (AgencyOutcome::getDost*JoinedAttribute),
    // not from a per-indicator field — a Program tagged to more than one
    // Strategy shows all of them joined, rather than guessing which one
    // applies to this specific indicator.
    public function group(Collection $indicators): array
    {
        $sorted = $indicators->sortBy(fn (OpcrIndicator $i) => [
            $i->agencyOutcome?->outcome ?? '',
            $i->agencyOutcome?->dost_pillar_names_joined ?? '',
            $i->agencyOutcome?->dost_strategy_names_joined ?? '',
            $i->agencyOutcome?->dost_sub_strategy_descriptions_joined ?? '',
            $i->id,
        ])->values();

        $rows = $sorted->map(fn (OpcrIndicator $i) => [
            'indicator' => $i,
            'pillar_text' => $i->agencyOutcome?->dost_pillar_names_joined,
            'strategy_text' => $i->agencyOutcome?->dost_strategy_names_joined,
            'sub_strategy_text' => $i->agencyOutcome?->dost_sub_strategy_descriptions_joined,
            'key' => [
                'program' => $i->agencyOutcome?->outcome,
                'pillar' => $i->agencyOutcome?->dost_pillar_names_joined,
                'strategy' => $i->agencyOutcome?->dost_strategy_names_joined,
                'sub_strategy' => $i->agencyOutcome?->dost_sub_strategy_descriptions_joined,
            ],
            'rowspan' => array_fill_keys(self::COLUMNS, 1),
            'show' => array_fill_keys(self::COLUMNS, true),
        ])->all();

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
