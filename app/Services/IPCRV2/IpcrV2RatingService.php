<?php

namespace App\Services\IPCRV2;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\IPCRWeightDistribution;

class IpcrV2RatingService
{
    private const DEFAULT_WEIGHTS = ['strategic' => 30, 'core' => 50, 'support' => 20];

    public function __construct(
        private StrategicFunctionService $strategic = new StrategicFunctionService()
    ) {}

    public function computeFinalRating(IpcrV2Record $record): ?float
    {
        $record->loadMissing(['coreItems', 'supportItems', 'user']);

        $weights = $this->weightsFor($record->user?->division_id);

        $strategicRating = $this->currentOpcrRating();
        $coreRating = $this->weightedCoreAverage($record->coreItems);
        $supportRating = $this->simpleAverage($record->supportItems->pluck('row_average'));

        if ($strategicRating === null && $coreRating === null && $supportRating === null) {
            return null;
        }

        $total = 0;
        $total += ($weights['strategic'] / 100) * ($strategicRating ?? 0);
        $total += ($weights['core'] / 100) * ($coreRating ?? 0);
        $total += ($weights['support'] / 100) * ($supportRating ?? 0);

        return round($total, 2);
    }

    public function adjectivalRating(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            $value >= 4.51 => 'Outstanding',
            $value >= 3.51 => 'Very Satisfactory',
            $value >= 2.51 => 'Satisfactory',
            $value >= 1.51 => 'Unsatisfactory',
            default => 'Poor',
        };
    }

    public function currentOpcrRating(): ?float
    {
        $indicators = $this->strategic->currentIndicators();
        $ratings = $indicators->pluck('rating_average')->filter(fn ($v) => $v !== null);

        return $ratings->isEmpty() ? null : round((float) $ratings->avg(), 2);
    }

    private function weightsFor(?int $divisionId): array
    {
        if (! $divisionId) {
            return self::DEFAULT_WEIGHTS;
        }

        $row = IPCRWeightDistribution::where('division_id', $divisionId)->first();

        return $row
            ? ['strategic' => (int) $row->strategic, 'core' => (int) $row->core, 'support' => (int) $row->support]
            : self::DEFAULT_WEIGHTS;
    }

    private function weightedCoreAverage($coreItems): ?float
    {
        $rated = $coreItems->filter(fn ($i) => $i->row_average !== null);
        if ($rated->isEmpty()) {
            return null;
        }

        $totalWeight = (float) $rated->sum('weight_percent');
        if ($totalWeight <= 0) {
            return $this->simpleAverage($rated->pluck('row_average'));
        }

        $weightedSum = $rated->sum(fn ($i) => (float) $i->row_average * (float) $i->weight_percent);

        return round($weightedSum / $totalWeight, 2);
    }

    private function simpleAverage($values): ?float
    {
        $values = collect($values)->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : round((float) $values->avg(), 2);
    }
}
