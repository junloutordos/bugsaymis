<?php

namespace App\Services\Atlas;

use App\Models\Atlas\AtlasModuleMaturityScore;

class AtlasMaturityService
{
    public function getScores(string $moduleKey): array
    {
        return AtlasModuleMaturityScore::where('module_key', $moduleKey)
            ->with('scorer:id,name')
            ->get()
            ->keyBy('dimension')
            ->map(fn ($s) => $s->toArray())
            ->toArray();
    }

    public function getOverallScore(string $moduleKey): float
    {
        $dimensions = config('atlas-modules.maturity_dimensions', []);
        $scores     = $this->getScores($moduleKey);
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($dimensions as $key => $dim) {
            $totalWeight += $dim['weight'];
            $weightedSum += ($scores[$key]['score'] ?? 0) * $dim['weight'];
        }

        return $totalWeight > 0 ? round($weightedSum / $totalWeight, 1) : 0.0;
    }

    public function getRadarData(string $moduleKey): array
    {
        $dimensions = config('atlas-modules.maturity_dimensions', []);
        $scores     = $this->getScores($moduleKey);

        return [
            'labels' => array_values(array_column($dimensions, 'label')),
            'data'   => array_values(array_map(fn ($k) => $scores[$k]['score'] ?? 0, array_keys($dimensions))),
            'colors' => array_values(array_column($dimensions, 'color')),
        ];
    }

    public function upsertScore(
        string $moduleKey,
        string $dimension,
        array $criteriaChecks,
        ?string $notes,
        int $scoredBy
    ): AtlasModuleMaturityScore {
        $criteriaCount = count(config("atlas-modules.maturity_dimensions.{$dimension}.criteria", [])) ?: 5;
        $checkedCount  = count(array_filter($criteriaChecks));
        $score         = (int) round(($checkedCount / $criteriaCount) * 100);

        return AtlasModuleMaturityScore::updateOrCreate(
            ['module_key' => $moduleKey, 'dimension' => $dimension],
            [
                'score'           => $score,
                'criteria_checks' => $criteriaChecks,
                'notes'           => $notes,
                'scored_by'       => $scoredBy,
                'scored_at'       => now(),
            ]
        );
    }
}
