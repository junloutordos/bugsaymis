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
            'core' => $record->coreItems->map(fn ($item) => [
                'label' => $item->label,
                'quality' => null,
                'efficiency' => null,
                'timeliness' => null,
                'average' => $item->row_average,
                'equivalent' => $this->rating->adjectivalRating($item->row_average ? (float) $item->row_average : null),
            ])->all(),
            'support' => $record->supportItems->map(fn ($item) => [
                'label' => $item->label,
                'quality' => $item->quality_rating,
                'efficiency' => $item->efficiency_rating,
                'timeliness' => $item->timeliness_rating,
                'average' => $item->row_average,
                'equivalent' => $this->rating->adjectivalRating($item->row_average ? (float) $item->row_average : null),
            ])->all(),
        ];
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
