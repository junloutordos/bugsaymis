<?php

namespace App\Services\HR;

use App\Models\HR\OnlinePunchNetworkRule;

/**
 * Resolves whether a request IP matches a known campus network range.
 * Deliberately conservative, same reasoning as AtlasSentinelNetworkLocationResolver:
 * several campus ISPs are dynamic/CGNAT, so a non-match is inconclusive, never
 * treated as proof of being off-campus. This signal is informational only —
 * callers must not use it to block or downgrade a punch on its own.
 */
class NetworkTrustService
{
    public function resolve(?string $ip): array
    {
        $rules = OnlinePunchNetworkRule::where('is_active', true)->get();

        if ($rules->isEmpty()) {
            return ['status' => 'unconfigured', 'matchedRuleId' => null];
        }

        if (! $ip) {
            return ['status' => 'unknown', 'matchedRuleId' => null];
        }

        foreach ($rules as $rule) {
            if ($this->ipInCidr($ip, $rule->cidr)) {
                return ['status' => 'trusted', 'matchedRuleId' => $rule->id];
            }
        }

        return ['status' => 'unknown', 'matchedRuleId' => null];
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $prefix] = explode('/', $cidr, 2);
        $prefix = (int) $prefix;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || $prefix < 0 || $prefix > 32) {
            return false;
        }

        $mask = $prefix === 0 ? 0 : (~0 << (32 - $prefix));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
