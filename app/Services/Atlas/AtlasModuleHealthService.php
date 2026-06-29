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
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $modules = collect(config('atlas-modules.modules', []))
            ->map(fn ($m) => $this->checkModule($m))
            ->values()
            ->all();

        $modules = $this->mergeRecentPings($modules);

        Cache::put(self::CACHE_KEY, $modules, self::CACHE_TTL);
        $this->logPings($modules);

        return $modules;
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

    public function logPings(array $modules): void
    {
        try {
            $now  = now();
            $rows = array_map(fn ($m) => [
                'module_key'    => $m['key'],
                'health_status' => $m['health_status'],
                'checked_at'    => $now,
            ], $modules);

            DB::table('atlas_health_pings')->insert($rows);

            // Prune pings older than 30 days to keep the table small
            DB::table('atlas_health_pings')
                ->where('checked_at', '<', now()->subDays(30))
                ->delete();
        } catch (\Throwable) {
            // Table may not exist yet (before migration)
        }
    }

    private function mergeRecentPings(array $modules): array
    {
        try {
            $keys = array_column($modules, 'key');

            $pings = DB::table('atlas_health_pings')
                ->whereIn('module_key', $keys)
                ->where('checked_at', '>=', now()->subDays(7))
                ->orderBy('module_key')
                ->orderBy('checked_at')
                ->get(['module_key', 'health_status'])
                ->groupBy('module_key')
                ->map(fn ($rows) => $rows->pluck('health_status')->slice(-7)->values()->toArray())
                ->toArray();

            return array_map(function ($m) use ($pings) {
                $m['recent_pings'] = $pings[$m['key']] ?? [];
                return $m;
            }, $modules);
        } catch (\Throwable) {
            return array_map(fn ($m) => array_merge($m, ['recent_pings' => []]), $modules);
        }
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

        $failedJobs = $this->countFailedJobs($module['queue_names'] ?? []);

        try {
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
