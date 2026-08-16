<?php

namespace App\Services\SPMS;

use App\Models\SPMS\WeightProfile;

class WeightProfileResolver
{
    private const DEFAULT_WEIGHTS = [
        'strategic_pct' => 30.0,
        'core_pct' => 50.0,
        'support_pct' => 20.0,
        'core_subweights' => null,
    ];

    public function resolve(string $level, ?int $divisionId, int $fiscalYear): array
    {
        if ($divisionId !== null) {
            $divisionProfile = WeightProfile::where('level', $level)
                ->where('division_id', $divisionId)
                ->where('fiscal_year', $fiscalYear)
                ->first();

            if ($divisionProfile) {
                return $this->toArray($divisionProfile);
            }
        }

        $defaultProfile = WeightProfile::where('level', $level)
            ->whereNull('division_id')
            ->where('fiscal_year', $fiscalYear)
            ->first();

        if ($defaultProfile) {
            return $this->toArray($defaultProfile);
        }

        return self::DEFAULT_WEIGHTS;
    }

    private function toArray(WeightProfile $profile): array
    {
        return [
            'strategic_pct' => (float) $profile->strategic_pct,
            'core_pct' => (float) $profile->core_pct,
            'support_pct' => (float) $profile->support_pct,
            'core_subweights' => $profile->core_subweights,
        ];
    }
}
