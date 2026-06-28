<?php

namespace App\Services\Atlas;

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
            fn () => [
                'technical'   => $this->technical($module),
                'usage'       => $this->usage($module),
                'operational' => $this->operational($module),
            ]
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
            'api_response_time' => [
                'label'        => 'API Response Time',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Add request timing middleware to enable',
            ],
            'error_rate' => [
                'label'        => 'Error Rate',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'CloudWatch Logs integration required',
            ],
            'db_query_perf' => [
                'label'        => 'DB Query Performance',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Enable slow query logging to enable',
            ],
            'cache_hit_rate' => [
                'label'        => 'Cache Hit Rate',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Cache instrumentation not available (database driver)',
            ],
            'uptime' => [
                'label'        => 'Uptime %',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Health ping history not yet collected',
            ],
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
                'label'  => 'Peak Usage Hours',
                'value'  => $peakHours,
                'unit'   => 'transactions/hour (last 30d)',
                'real'   => $peakHours !== null,
                'note'   => 'Module transactions grouped by hour',
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
            'session_duration' => [
                'label'        => 'Avg Session Duration',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Per-session duration tracking not enabled',
            ],
            'feature_adoption' => [
                'label'        => 'Feature Adoption',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'In-page event tracking required',
            ],
            'user_retention' => [
                'label'        => 'User Retention',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Cohort analysis requires session attribution',
            ],
        ];
    }

    private function operational(array $module): array
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
            'sla_compliance' => [
                'label'        => 'SLA Compliance',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Define SLA hours in module settings to enable',
            ],
            'sync_delays' => [
                'label'        => 'Sync Delays',
                'value'        => null,
                'status'       => 'stub',
                'real'         => false,
                'stub_message' => 'Sync monitoring not configured',
            ],
        ];
    }

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
