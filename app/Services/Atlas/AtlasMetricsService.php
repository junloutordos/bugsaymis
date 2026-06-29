<?php

namespace App\Services\Atlas;

use App\Models\Atlas\AtlasModuleSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AtlasMetricsService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function getModuleMetrics(array $module): array
    {
        return Cache::remember(
            "atlas.metrics.{$module['key']}",
            self::CACHE_TTL,
            function () use ($module) {
                $slaHours = AtlasModuleSetting::where('module_key', $module['key'])->value('sla_hours');

                return [
                    'technical'   => $this->technical($module),
                    'usage'       => $this->usage($module),
                    'operational' => $this->operational($module, $slaHours),
                ];
            }
        );
    }

    public function clearModuleCache(string $key): void
    {
        Cache::forget("atlas.metrics.{$key}");
    }

    private function technical(array $module): array
    {
        $queueBacklog   = $this->countQueueBacklog($module['queue_names'] ?? []);
        $failedJobs     = $module['failed_jobs_count'] ?? 0;
        $activeSessions = $this->countActiveSessions();
        $failedLogins   = $this->countFailedLogins();
        $backupOk       = ! empty(env('GOOGLE_DRIVE_FOLDER_ID'));
        $uptime         = $this->computeUptime($module['key']);
        $requestStats   = $this->computeRequestStats();

        return [
            'queue_backlog' => [
                'label'  => 'Queue Backlog',
                'value'  => $queueBacklog,
                'unit'   => 'jobs',
                'status' => $queueBacklog === 0 ? 'ok' : ($queueBacklog < 20 ? 'warn' : 'error'),
                'real'   => true,
            ],
            'failed_jobs' => [
                'label'  => 'Failed Jobs',
                'value'  => $failedJobs,
                'unit'   => '',
                'status' => $failedJobs === 0 ? 'ok' : 'error',
                'real'   => true,
            ],
            'active_sessions' => [
                'label'  => 'Active Sessions',
                'value'  => $activeSessions,
                'unit'   => 'users (30 min)',
                'status' => 'ok',
                'real'   => true,
            ],
            'failed_logins_24h' => [
                'label'  => 'Failed Logins (24h)',
                'value'  => $failedLogins,
                'unit'   => '',
                'status' => $failedLogins === 0 ? 'ok' : ($failedLogins < 10 ? 'warn' : 'error'),
                'real'   => true,
            ],
            'backup_status' => [
                'label'  => 'Backup Configured',
                'value'  => $backupOk ? 'Configured' : 'Not configured',
                'unit'   => '',
                'status' => $backupOk ? 'ok' : 'warn',
                'real'   => true,
                'note'   => 'Run backup:verify for actual last-backup time',
            ],
            'uptime' => $uptime !== null ? [
                'label'  => 'Uptime %',
                'value'  => $uptime,
                'unit'   => '(last 7 days, health refreshes)',
                'status' => $uptime >= 95 ? 'ok' : ($uptime >= 80 ? 'warn' : 'error'),
                'real'   => true,
                'note'   => 'Healthy + Info pings / total pings',
            ] : [
                'label'        => 'Uptime %',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Refresh health data to start collecting ping history',
            ],
            'api_response_time' => $requestStats['api_response_time'],
            'error_rate'        => $requestStats['error_rate'],
            'db_query_perf'     => $requestStats['db_query_perf'],
            'cache_hit_rate'    => $requestStats['cache_hit_rate'],
        ];
    }

    private function usage(array $module): array
    {
        $table = $module['primary_table'] ?? null;
        $col   = array_key_exists('activity_col', $module) ? $module['activity_col'] : 'created_at';

        $todayStart    = today('Asia/Manila')->startOfDay()->timestamp;
        $sevenDaysAgo  = now()->subDays(7)->timestamp;
        $thirtyDaysAgo = now()->subDays(30)->timestamp;

        $dau = DB::table('sessions')
            ->where('last_activity', '>=', $todayStart)
            ->whereNotNull('user_id')
            ->distinct('user_id')->count('user_id');

        $wau = DB::table('sessions')
            ->where('last_activity', '>=', $sevenDaysAgo)
            ->whereNotNull('user_id')
            ->distinct('user_id')->count('user_id');

        $mau = DB::table('sessions')
            ->where('last_activity', '>=', $thirtyDaysAgo)
            ->whereNotNull('user_id')
            ->distinct('user_id')->count('user_id');

        $peakHours = null;
        if ($table !== null && $col !== null) {
            try {
                $rows = DB::table($table)
                    ->selectRaw('HOUR(' . $col . ') as hour, COUNT(*) as count')
                    ->where($col, '>=', now()->subDays(30))
                    ->groupBy('hour')
                    ->orderBy('hour')
                    ->pluck('count', 'hour')
                    ->toArray();

                $peakHours = array_replace(array_fill(0, 24, 0), $rows);
                ksort($peakHours);
                $peakHours = array_values($peakHours);
            } catch (\Throwable) {
                $peakHours = null;
            }
        }

        return [
            'dau' => [
                'label'  => 'Daily Active Users',
                'value'  => $dau,
                'unit'   => 'users today',
                'real'   => true,
                'note'   => 'System-wide (all modules)',
            ],
            'wau' => [
                'label'  => 'Weekly Active Users',
                'value'  => $wau,
                'unit'   => 'users (7d)',
                'real'   => true,
                'note'   => 'System-wide',
            ],
            'mau' => [
                'label'  => 'Monthly Active Users',
                'value'  => $mau,
                'unit'   => 'users (30d)',
                'real'   => true,
                'note'   => 'System-wide',
            ],
            'peak_hours' => [
                'label'        => 'Peak Usage Hours',
                'value'        => $peakHours,
                'unit'         => 'transactions/hour (last 30d)',
                'real'         => $peakHours !== null,
                'note'         => 'Module transactions grouped by hour',
                'stub_message' => $peakHours === null ? 'No timestamp column available for this module' : null,
            ],
            'transactions_7d' => [
                'label' => 'Transactions (7d)',
                'value' => $module['activity_count_7d'] ?? null,
                'real'  => true,
            ],
            'transactions_30d' => [
                'label' => 'Transactions (30d)',
                'value' => $module['activity_count_30d'] ?? null,
                'real'  => true,
            ],
            'session_duration'  => $this->computeSessionDuration(),
            'feature_adoption'  => $this->computeFeatureAdoption($module),
            'user_retention'    => $this->computeUserRetention(),
        ];
    }

    private function operational(array $module, ?int $slaHours = null): array
    {
        $pendingApprovals = $this->countPendingApprovals($module['pending_approvals'] ?? []);
        $queueBacklog     = $this->countQueueBacklog($module['queue_names'] ?? []);
        $failedWorkflows  = $module['failed_jobs_count'] ?? 0;

        $notifsSent   = 0;
        $notifsUnread = 0;
        try {
            $notifsSent   = DB::table('notifications')->where('created_at', '>=', now()->subDays(7))->count();
            $notifsUnread = DB::table('notifications')->whereNull('read_at')->count();
        } catch (\Throwable) {
        }

        return [
            'pending_approvals' => [
                'label'   => 'Pending Approvals',
                'value'   => $pendingApprovals,
                'unit'    => '',
                'status'  => $pendingApprovals === 0 ? 'ok' : 'warn',
                'real'    => true,
                'details' => $module['pending_approvals'] ?? [],
            ],
            'queue_backlog' => [
                'label'  => 'Queue Backlog',
                'value'  => $queueBacklog,
                'unit'   => 'jobs',
                'status' => $queueBacklog === 0 ? 'ok' : 'warn',
                'real'   => true,
            ],
            'failed_workflows' => [
                'label'  => 'Failed Workflows',
                'value'  => $failedWorkflows,
                'unit'   => '',
                'status' => $failedWorkflows === 0 ? 'ok' : 'error',
                'real'   => true,
            ],
            'notifications_sent_7d' => [
                'label'  => 'Notifications Sent (7d)',
                'value'  => $notifsSent,
                'unit'   => '',
                'real'   => true,
                'note'   => 'System-wide',
            ],
            'notifications_unread' => [
                'label'  => 'Unread Notifications',
                'value'  => $notifsUnread,
                'unit'   => '',
                'real'   => true,
                'note'   => 'System-wide',
            ],
            'sla_compliance' => $this->computeSlaCompliance($module, $slaHours),
            'sync_delays'    => $this->computeSyncDelays($module),
        ];
    }

    // ── Real metric computers ────────────────────────────────────────────────

    private function computeRequestStats(): array
    {
        $noData = fn(string $label, string $unit = '') => [
            'label'        => $label,
            'value'        => null,
            'unit'         => $unit,
            'status'       => 'stub',
            'real'         => false,
            'stub_message' => 'No data yet — stats accumulate as users navigate (system-wide, 24h)',
        ];

        try {
            $row = DB::table('atlas_request_stats')
                ->where('hour_bucket', '>=', now()->subHours(24)->startOfHour())
                ->selectRaw('
                    SUM(request_count)     as total_requests,
                    SUM(error_count)       as total_errors,
                    SUM(total_duration_ms) as total_ms,
                    SUM(cache_hits)        as total_hits,
                    SUM(cache_misses)      as total_misses,
                    SUM(query_count)       as total_queries,
                    SUM(total_query_ms)    as total_query_ms
                ')
                ->first();

            $totalRequests = (int) ($row->total_requests ?? 0);

            if ($totalRequests === 0) {
                return [
                    'api_response_time' => $noData('API Response Time', 'ms avg (24h)'),
                    'error_rate'        => $noData('Error Rate', '% (24h)'),
                    'db_query_perf'     => $noData('DB Query Performance', 'ms avg/query (24h)'),
                    'cache_hit_rate'    => $noData('Cache Hit Rate', '% (24h)'),
                ];
            }

            $avgMs    = (int) round((int) $row->total_ms / $totalRequests);
            $errorPct = round(((int) $row->total_errors / $totalRequests) * 100, 2);

            $totalQueries = (int) ($row->total_queries ?? 0);
            $avgQueryMs   = $totalQueries > 0
                ? round((float) $row->total_query_ms / $totalQueries, 1)
                : null;

            $totalHits   = (int) ($row->total_hits ?? 0);
            $totalMisses = (int) ($row->total_misses ?? 0);
            $cacheTotal  = $totalHits + $totalMisses;
            $cacheHitPct = $cacheTotal > 0
                ? round($totalHits / $cacheTotal * 100, 1)
                : null;

            return [
                'api_response_time' => [
                    'label'  => 'API Response Time',
                    'value'  => $avgMs,
                    'unit'   => 'ms avg (24h)',
                    'status' => $avgMs < 500 ? 'ok' : ($avgMs < 2000 ? 'warn' : 'error'),
                    'real'   => true,
                    'note'   => number_format($totalRequests) . ' requests sampled (system-wide)',
                ],
                'error_rate' => [
                    'label'  => 'Error Rate',
                    'value'  => $errorPct,
                    'unit'   => '% 5xx (24h)',
                    'status' => $errorPct < 1 ? 'ok' : ($errorPct < 5 ? 'warn' : 'error'),
                    'real'   => true,
                    'note'   => (int) $row->total_errors . ' errors of ' . number_format($totalRequests) . ' requests',
                ],
                'db_query_perf' => $avgQueryMs !== null ? [
                    'label'  => 'DB Query Performance',
                    'value'  => $avgQueryMs,
                    'unit'   => 'ms avg/query (24h)',
                    'status' => $avgQueryMs < 50 ? 'ok' : ($avgQueryMs < 200 ? 'warn' : 'error'),
                    'real'   => true,
                    'note'   => number_format($totalQueries) . ' queries tracked',
                ] : $noData('DB Query Performance', 'ms avg/query (24h)'),
                'cache_hit_rate' => $cacheHitPct !== null ? [
                    'label'  => 'Cache Hit Rate',
                    'value'  => $cacheHitPct,
                    'unit'   => '% (24h)',
                    'status' => $cacheHitPct >= 70 ? 'ok' : ($cacheHitPct >= 40 ? 'warn' : 'error'),
                    'real'   => true,
                    'note'   => "{$totalHits} hits, {$totalMisses} misses",
                ] : $noData('Cache Hit Rate', '% (24h)'),
            ];
        } catch (\Throwable) {
            $err = fn(string $label) => ['label' => $label, 'value' => null, 'status' => 'stub', 'real' => false, 'stub_message' => 'Stats table not available'];
            return [
                'api_response_time' => $err('API Response Time'),
                'error_rate'        => $err('Error Rate'),
                'db_query_perf'     => $err('DB Query Performance'),
                'cache_hit_rate'    => $err('Cache Hit Rate'),
            ];
        }
    }

    private function computeSessionDuration(): array
    {
        try {
            $avgMinutes = DB::table('sessions')
                ->whereNotNull('started_at')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subDays(7)->timestamp)
                ->whereRaw('last_activity > UNIX_TIMESTAMP(started_at)')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, FROM_UNIXTIME(last_activity))) as avg_min')
                ->value('avg_min');

            if ($avgMinutes === null) {
                return [
                    'label'        => 'Avg Session Duration',
                    'value'        => null,
                    'status'       => 'stub',
                    'real'         => false,
                    'stub_message' => 'No session duration data yet — tracking begins on next login',
                ];
            }

            $minutes = (int) round((float) $avgMinutes);
            return [
                'label'  => 'Avg Session Duration',
                'value'  => $minutes,
                'unit'   => 'min (last 7d)',
                'status' => 'ok',
                'real'   => true,
                'note'   => 'Time from first page load to last activity per session',
            ];
        } catch (\Throwable) {
            return [
                'label'        => 'Avg Session Duration',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Session tracking column not available yet',
            ];
        }
    }

    private function computeFeatureAdoption(array $module): array
    {
        $table   = $module['primary_table'] ?? null;
        $userCol = $module['user_col'] ?? null;
        $actCol  = array_key_exists('activity_col', $module) ? $module['activity_col'] : 'created_at';

        if ($table === null || $userCol === null) {
            return [
                'label'        => 'Feature Adoption',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Module does not track individual user activity',
            ];
        }

        try {
            // Sanitise column name (config-provided, not user input — just be safe)
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $userCol);

            $activeModuleUsers = DB::table($table)
                ->whereNotNull($safeCol)
                ->where($actCol, '>=', now()->subDays(30))
                ->distinct($safeCol)
                ->count($safeCol);

            $totalActiveUsers = DB::table('sessions')
                ->where('last_activity', '>=', now()->subDays(30)->timestamp)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');

            if ($totalActiveUsers === 0) {
                return [
                    'label'  => 'Feature Adoption',
                    'value'  => 0,
                    'unit'   => '% of active users (30d)',
                    'status' => 'ok',
                    'real'   => true,
                ];
            }

            $pct = round(($activeModuleUsers / $totalActiveUsers) * 100, 1);
            return [
                'label'  => 'Feature Adoption',
                'value'  => $pct,
                'unit'   => '% of active users (30d)',
                'status' => $pct >= 20 ? 'ok' : 'warn',
                'real'   => true,
                'note'   => "{$activeModuleUsers} of {$totalActiveUsers} active users used this module",
            ];
        } catch (\Throwable) {
            return [
                'label'        => 'Feature Adoption',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Could not compute adoption rate',
            ];
        }
    }

    private function computeUserRetention(): array
    {
        try {
            // Cohort: users active 60–30 days ago
            $cohortIds = DB::table('audit_logs')
                ->where('created_at', '>=', now()->subDays(60))
                ->where('created_at', '<', now()->subDays(30))
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->pluck('user_id');

            if ($cohortIds->isEmpty()) {
                return [
                    'label'  => 'User Retention',
                    'value'  => null,
                    'status' => 'ok',
                    'real'   => true,
                    'unit'   => '% retained (30d cohort)',
                    'note'   => 'Insufficient cohort data (audit log < 60 days old)',
                ];
            }

            $retained = DB::table('audit_logs')
                ->where('created_at', '>=', now()->subDays(30))
                ->whereIn('user_id', $cohortIds)
                ->distinct('user_id')
                ->count('user_id');

            $cohortSize = $cohortIds->count();
            $pct        = round(($retained / $cohortSize) * 100, 1);

            return [
                'label'  => 'User Retention',
                'value'  => $pct,
                'unit'   => '% retained (30d cohort)',
                'status' => $pct >= 70 ? 'ok' : ($pct >= 40 ? 'warn' : 'error'),
                'real'   => true,
                'note'   => "{$retained} of {$cohortSize} users from 60–30d ago returned in last 30d",
            ];
        } catch (\Throwable) {
            return [
                'label'        => 'User Retention',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Cohort analysis requires audit_logs table',
            ];
        }
    }

    private function computeSyncDelays(array $module): array
    {
        $syncCheck = $module['sync_check'] ?? null;
        $table     = $module['primary_table'] ?? null;

        if ($syncCheck === null || $table === null) {
            return [
                'label'        => 'Sync Delays',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Sync monitoring not configured for this module',
            ];
        }

        try {
            $column      = preg_replace('/[^a-zA-Z0-9_]/', '', $syncCheck['column']);
            $value       = $syncCheck['value'];
            $maxGapHours = (int) ($syncCheck['max_gap_hours'] ?? 4);
            $label       = $syncCheck['label'] ?? 'Sync';

            $latest = DB::table($table)->where($column, $value)->max('created_at');

            if ($latest === null) {
                return [
                    'label'  => 'Sync Delays',
                    'value'  => null,
                    'status' => 'ok',
                    'real'   => true,
                    'unit'   => "h since last {$label} sync",
                    'note'   => "No {$label} records found",
                ];
            }

            $hoursAgo = round(Carbon::parse($latest)->diffInMinutes(now()) / 60, 1);
            $status   = $hoursAgo <= $maxGapHours
                ? 'ok'
                : ($hoursAgo <= $maxGapHours * 2 ? 'warn' : 'error');

            return [
                'label'  => 'Sync Delays',
                'value'  => $hoursAgo,
                'unit'   => "h since last {$label} sync (target ≤ {$maxGapHours}h)",
                'status' => $status,
                'real'   => true,
                'note'   => 'Last ' . $label . ' record: ' . Carbon::parse($latest)->format('Y-m-d H:i'),
            ];
        } catch (\Throwable) {
            return [
                'label'        => 'Sync Delays',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Sync delay computation error',
            ];
        }
    }

    // ── Uptime & SLA ────────────────────────────────────────────────────────

    private function computeUptime(string $moduleKey): ?float
    {
        try {
            $total = DB::table('atlas_health_pings')
                ->where('module_key', $moduleKey)
                ->where('checked_at', '>=', now()->subDays(7))
                ->count();

            if ($total === 0) {
                return null;
            }

            // 'info' status (computed/read-only modules) counts as up
            $healthy = DB::table('atlas_health_pings')
                ->where('module_key', $moduleKey)
                ->where('checked_at', '>=', now()->subDays(7))
                ->whereIn('health_status', ['healthy', 'info'])
                ->count();

            return round(($healthy / $total) * 100, 1);
        } catch (\Throwable) {
            return null;
        }
    }

    private function computeSlaCompliance(array $module, ?int $slaHours): array
    {
        $pendingApprovals = $module['pending_approvals'] ?? [];

        if ($slaHours === null || empty($pendingApprovals)) {
            return [
                'label'        => 'SLA Compliance',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => empty($pendingApprovals)
                    ? 'No approval workflow configured for this module'
                    : 'Set SLA hours in Operations → Module Settings to enable',
            ];
        }

        $cfg   = $pendingApprovals[0];
        $table = $cfg['table'];
        $thirtyDaysAgo = now()->subDays(30);

        try {
            $resolvedCondition = function ($q) use ($cfg) {
                if (isset($cfg['pending_null_col'])) {
                    return $q->whereNotNull($cfg['pending_null_col']);
                }
                if (isset($cfg['status_col'])) {
                    return $q->whereNotIn($cfg['status_col'], $cfg['pending_values'] ?? []);
                }
                return $q;
            };

            $totalResolved = $resolvedCondition(
                DB::table($table)->where('created_at', '>=', $thirtyDaysAgo)
            )->count();

            if ($totalResolved === 0) {
                return [
                    'label'  => 'SLA Compliance',
                    'value'  => null,
                    'status' => 'ok',
                    'real'   => true,
                    'note'   => 'No resolved records in last 30 days',
                    'unit'   => "(within {$slaHours}h SLA)",
                ];
            }

            $withinSla = $resolvedCondition(
                DB::table($table)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, updated_at) <= ?', [$slaHours])
            )->count();

            $pct = round(($withinSla / $totalResolved) * 100, 1);

            return [
                'label'  => 'SLA Compliance',
                'value'  => $pct,
                'unit'   => "% within {$slaHours}h SLA (last 30d)",
                'status' => $pct >= 80 ? 'ok' : ($pct >= 50 ? 'warn' : 'error'),
                'real'   => true,
                'note'   => "{$withinSla} of {$totalResolved} resolved within {$slaHours}h",
            ];
        } catch (\Throwable) {
            return [
                'label'        => 'SLA Compliance',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'SLA computation error — check table schema',
            ];
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function countActiveSessions(): int
    {
        try {
            return DB::table('sessions')
                ->where('last_activity', '>=', now()->subMinutes(30)->timestamp)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countFailedLogins(): int
    {
        try {
            return DB::table('audit_logs')
                ->where('action', 'login_failed')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countQueueBacklog(array $queueNames): int
    {
        try {
            $query = DB::table('jobs');
            if (! empty($queueNames)) {
                $query->whereIn('queue', $queueNames);
            }
            return $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countPendingApprovals(array $approvalConfigs): int
    {
        $total = 0;
        foreach ($approvalConfigs as $cfg) {
            try {
                $query = DB::table($cfg['table']);
                if (isset($cfg['pending_null_col'])) {
                    $query->whereNull($cfg['pending_null_col']);
                } else {
                    $query->whereIn($cfg['status_col'], $cfg['pending_values']);
                }
                $total += $query->count();
            } catch (\Throwable) {
                // Skip if table/column doesn't exist
            }
        }
        return $total;
    }
}
