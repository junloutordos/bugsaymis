<?php

namespace App\Services\Atlas;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AtlasModuleHealthService
{
    private const CACHE_KEY = 'atlas.modules.health';
    private const CACHE_TTL = 300; // 5 minutes

    public function getAllModuleHealth(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return collect(config('atlas-modules.modules', []))
                ->map(fn ($m) => $this->checkModule($m))
                ->values()
                ->all();
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function getSummary(array $modules): array
    {
        $counts      = collect($modules)->countBy('health_status');
        $maturityDist = collect($modules)->countBy('maturity');

        return [
            'total'    => count($modules),
            'healthy'  => $counts['healthy']  ?? 0,
            'idle'     => $counts['idle']     ?? 0,
            'degraded' => $counts['degraded'] ?? 0,
            'critical' => $counts['critical'] ?? 0,
            'info'     => $counts['info']     ?? 0,
            'maturity' => $maturityDist->all(),
        ];
    }

    public function getIntegrationStatus(): array
    {
        return collect(config('atlas-modules.integrations', []))
            ->map(fn ($integration, $key) => array_merge($integration, [
                'key'        => $key,
                'configured' => ! empty(env($integration['env_key'])),
            ]))
            ->values()
            ->all();
    }

    private function checkModule(array $module): array
    {
        $table = $module['primary_table'] ?? null;
        // Use array_key_exists so that an explicit null activity_col is preserved
        // (null means "no timestamp column — use row count instead").
        $col   = array_key_exists('activity_col', $module) ? $module['activity_col'] : 'created_at';

        // Modules with no trackable table (read-only/computed dashboards)
        if ($table === null) {
            return array_merge($module, [
                'health_status'      => 'info',
                'failed_jobs_count'  => 0,
                'activity_count_30d' => null,
                'activity_count_7d'  => null,
                'last_activity_at'   => null,
                'health_checked_at'  => now()->toISOString(),
            ]);
        }

        // Check failed jobs before the table query so we still get the count
        // even if the module table itself throws.
        $failedJobs = $this->countFailedJobs($module['queue_names'] ?? []);

        try {
            // Null activity_col means the table has no timestamp column (e.g. EGCU static data).
            // Fall back to a plain row count: any rows → healthy, zero rows → idle.
            if ($col === null) {
                $totalRows    = DB::table($table)->count();
                $activity30d  = null;
                $activity7d   = null;
                $lastActivity = null;

                $status = match (true) {
                    $failedJobs > 0  => 'degraded',
                    $totalRows > 0   => 'healthy',
                    default          => 'idle',
                };
            } else {
                $activity30d  = DB::table($table)->where($col, '>=', now()->subDays(30))->count();
                $activity7d   = DB::table($table)->where($col, '>=', now()->subDays(7))->count();
                $lastActivity = DB::table($table)->orderByDesc($col)->value($col);

                $status = match (true) {
                    $failedJobs > 0  => 'degraded',
                    $activity30d > 0 => 'healthy',
                    default          => 'idle',
                };
            }
        } catch (\Throwable) {
            // Table missing, wrong column, or connection error → surface as critical
            $status       = 'critical';
            $activity30d  = null;
            $activity7d   = null;
            $lastActivity = null;
        }

        return array_merge($module, [
            'health_status'      => $status,
            'failed_jobs_count'  => $failedJobs,
            'activity_count_30d' => $activity30d ?? null,
            'activity_count_7d'  => $activity7d  ?? null,
            'last_activity_at'   => $lastActivity ?? null,
            'health_checked_at'  => now()->toISOString(),
        ]);
    }

    private function countFailedJobs(array $queueNames): int
    {
        if (empty($queueNames)) {
            return 0;
        }

        try {
            return DB::table('failed_jobs')->whereIn('queue', $queueNames)->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
