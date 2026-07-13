<?php

namespace App\Exports\AMS\Concerns;

trait FormatsLikertOptions
{
    private function formatOption(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ucwords(str_replace('_', ' ', $value));
    }

    private function formatDistribution(array $dist, array $options): string
    {
        $parts = [];
        foreach ($options as $opt) {
            $parts[] = $this->formatOption($opt) . ': ' . ($dist[$opt] ?? 0);
        }

        return implode('; ', $parts);
    }
}
