<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;

class IpcrV2SummaryService
{
    public function __construct(
        private StrategicFunctionService $strategic = new StrategicFunctionService(),
        private IpcrV2RatingService $rating = new IpcrV2RatingService()
    ) {}

    public function buildRows(IpcrV2Record $record): array
    {
        $record->loadMissing(['coreItems', 'supportItems']);

        return [
            'strategic' => $this->strategicRows(),
            'core' => $this->functionRows($record->coreItems),
            'support' => $this->functionRows($record->supportItems),
        ];
    }

    /**
     * One summary row per underlying Core/Support Function, averaging
     * across its materialized items when it's tagged to multiple WDPs —
     * the item tables above show the per-WDP breakdown, but the Rating
     * Summary (screen and print) reports at the function level, same as
     * v1. Items with no employee_function_id (e.g. ad-hoc rows with no
     * linked function) are never merged with each other.
     */
    private function functionRows($items): array
    {
        $rows = [];
        $groupIndexByFunctionId = [];

        foreach ($items as $item) {
            $functionId = $item->employee_function_id;
            $key = $functionId !== null && isset($groupIndexByFunctionId[$functionId])
                ? $groupIndexByFunctionId[$functionId]
                : null;

            if ($key === null) {
                $rows[] = ['label' => $item->label, 'items' => collect()];
                $key = array_key_last($rows);
                if ($functionId !== null) {
                    $groupIndexByFunctionId[$functionId] = $key;
                }
            }

            $rows[$key]['items']->push($item);
        }

        return collect($rows)->map(function ($row) {
            $avg = fn ($field) => $row['items']->pluck($field)->filter(fn ($v) => $v !== null)->avg();
            $average = $avg('row_average');

            return [
                'label' => $row['label'],
                'quality' => $avg('quality_rating'),
                'efficiency' => $avg('efficiency_rating'),
                'timeliness' => $avg('timeliness_rating'),
                'average' => $average,
                'equivalent' => $this->rating->adjectivalRating($average !== null ? (float) $average : null),
            ];
        })->values()->all();
    }

    private function strategicRows(): array
    {
        return $this->strategic->currentIndicators()
            ->groupBy(fn ($i) => $i->agencyOutcome?->outcome ?? '—')
            ->map(function ($indicators, $programLabel) {
                $average = $indicators->pluck('rating_average')->filter(fn ($v) => $v !== null)->avg();

                return [
                    'label' => $programLabel,
                    'quality' => $indicators->pluck('rating_quality')->filter(fn ($v) => $v !== null)->avg(),
                    'efficiency' => $indicators->pluck('rating_efficiency')->filter(fn ($v) => $v !== null)->avg(),
                    'timeliness' => $indicators->pluck('rating_timeliness')->filter(fn ($v) => $v !== null)->avg(),
                    'average' => $average,
                    'equivalent' => $this->rating->adjectivalRating($average ? (float) $average : null),
                ];
            })
            ->values()
            ->all();
    }
}
