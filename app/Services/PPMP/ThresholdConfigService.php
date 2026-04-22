<?php

namespace App\Services\PPMP;

use App\Models\PPMP\PpmpSetting;

class ThresholdConfigService
{
    private const DEFAULTS = [
        'shopping_goods'               => 1000000,
        'shopping_infrastructure'      => 5000000,
        'shopping_consulting_services' => null,
        'svp_goods'                    => 1000000,
        'svp_infrastructure'           => 5000000,
        'svp_consulting_services'      => 1000000,
    ];

    /**
     * Get the threshold amount for a procurement method + category.
     * Returns null if no threshold applies.
     */
    public function getThreshold(string $method, string $category): ?float
    {
        $key = "threshold_{$method}_{$category}";
        $dbValue = PpmpSetting::getValue($key);

        if ($dbValue !== null) {
            return (float) $dbValue;
        }

        return self::DEFAULTS["{$method}_{$category}"] ?? null;
    }

    /**
     * Check if a procurement method requires justification in remarks.
     */
    public function requiresJustification(string $method): bool
    {
        return $method === 'direct_contracting';
    }

    /**
     * Get all thresholds (defaults merged with DB overrides).
     */
    public function getAllThresholds(): array
    {
        $thresholds = self::DEFAULTS;

        $dbSettings = PpmpSetting::where('key', 'like', 'threshold_%')->pluck('value', 'key');
        foreach ($dbSettings as $key => $value) {
            $shortKey = str_replace('threshold_', '', $key);
            $thresholds[$shortKey] = $value !== null ? (float) $value : null;
        }

        return $thresholds;
    }

    /**
     * Update a threshold value.
     */
    public function updateThreshold(string $method, string $category, ?float $amount): void
    {
        PpmpSetting::setValue("threshold_{$method}_{$category}", $amount);
    }
}
