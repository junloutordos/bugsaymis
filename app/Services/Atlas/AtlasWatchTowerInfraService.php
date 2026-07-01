<?php

namespace App\Services\Atlas;

use Aws\CloudWatch\CloudWatchClient;
use Aws\XRay\XRayClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AtlasWatchTowerInfraService
{
    private const CACHE_TTL = 60; // 1 minute — this page isn't hit often enough to need longer

    private const LOAD_BALANCER = 'app/crcmis-alb/abc7fe31f3f2e539';

    private const RDS_INSTANCE = 'crcmis-db-encrypted';

    private const HTTP_TIMEOUT = 5; // seconds — never let a slow AWS API hang this page

    public function getInfraSummary(): array
    {
        return Cache::remember('atlas.watchtower.infra', self::CACHE_TTL, function () {
            return [
                'alb' => $this->rescue(fn () => $this->albMetrics()),
                'ecsWeb' => $this->rescue(fn () => $this->ecsMetrics('crcmis-prod-service')),
                'ecsWorker' => $this->rescue(fn () => $this->ecsMetrics('crcmis-prod-worker')),
                'rds' => $this->rescue(fn () => $this->rdsMetrics()),
                'traces' => $this->rescue(fn () => $this->traceSummary()),
            ];
        });
    }

    private function cloudWatch(): CloudWatchClient
    {
        return new CloudWatchClient([
            'version' => 'latest',
            'region' => 'ap-southeast-1',
            'http' => ['timeout' => self::HTTP_TIMEOUT, 'connect_timeout' => self::HTTP_TIMEOUT],
        ]);
    }

    private function albMetrics(): array
    {
        $end = now();
        $start = $end->clone()->subHours(24);

        $result = $this->cloudWatch()->getMetricData([
            'StartTime' => $start,
            'EndTime' => $end,
            'MetricDataQueries' => [
                $this->query('requests', 'RequestCount', 'AWS/ApplicationELB', [['Name' => 'LoadBalancer', 'Value' => self::LOAD_BALANCER]], 'Sum'),
                $this->query('errors5xx', 'HTTPCode_Target_5XX_Count', 'AWS/ApplicationELB', [['Name' => 'LoadBalancer', 'Value' => self::LOAD_BALANCER]], 'Sum'),
                $this->query('latencyP99', 'TargetResponseTime', 'AWS/ApplicationELB', [['Name' => 'LoadBalancer', 'Value' => self::LOAD_BALANCER]], 'p99'),
            ],
        ]);

        return $this->shapeTimeSeries($result);
    }

    private function ecsMetrics(string $serviceName): array
    {
        $end = now();
        $start = $end->clone()->subHours(24);
        $dims = [
            ['Name' => 'ServiceName', 'Value' => $serviceName],
            ['Name' => 'ClusterName', 'Value' => 'crcmis-prod'],
        ];

        $result = $this->cloudWatch()->getMetricData([
            'StartTime' => $start,
            'EndTime' => $end,
            'MetricDataQueries' => [
                $this->query('cpu', 'CPUUtilization', 'AWS/ECS', $dims, 'Average'),
                $this->query('memory', 'MemoryUtilization', 'AWS/ECS', $dims, 'Average'),
            ],
        ]);

        return $this->shapeTimeSeries($result);
    }

    private function rdsMetrics(): array
    {
        $end = now();
        $start = $end->clone()->subHours(24);
        $dims = [['Name' => 'DBInstanceIdentifier', 'Value' => self::RDS_INSTANCE]];

        $result = $this->cloudWatch()->getMetricData([
            'StartTime' => $start,
            'EndTime' => $end,
            'MetricDataQueries' => [
                $this->query('cpu', 'CPUUtilization', 'AWS/RDS', $dims, 'Average'),
                $this->query('connections', 'DatabaseConnections', 'AWS/RDS', $dims, 'Average'),
                $this->query('freeableMemoryMb', 'FreeableMemory', 'AWS/RDS', $dims, 'Average'),
                $this->query('freeStorageGb', 'FreeStorageSpace', 'AWS/RDS', $dims, 'Average'),
            ],
        ]);

        return $this->shapeTimeSeries($result, [
            'freeableMemoryMb' => 1024 * 1024,
            'freeStorageGb' => 1024 * 1024 * 1024,
        ]);
    }

    private function traceSummary(): array
    {
        $client = new XRayClient([
            'version' => 'latest',
            'region' => 'ap-southeast-1',
            'http' => ['timeout' => self::HTTP_TIMEOUT, 'connect_timeout' => self::HTTP_TIMEOUT],
        ]);

        $end = now();
        $start = $end->clone()->subHour();

        $summaries = [];
        $nextToken = null;

        do {
            $params = [
                'StartTime' => $start,
                'EndTime' => $end,
                'TimeRangeType' => 'Event',
            ];
            if ($nextToken) {
                $params['NextToken'] = $nextToken;
            }

            $result = $client->getTraceSummaries($params);
            $summaries = array_merge($summaries, $result['TraceSummaries'] ?? []);
            $nextToken = $result['NextToken'] ?? null;
        } while ($nextToken && count($summaries) < 1000);

        $total = count($summaries);
        $errors = collect($summaries)->filter(fn ($t) => ($t['HasError'] ?? false) || ($t['HasFault'] ?? false))->count();
        $avgDurationMs = $total > 0
            ? round(collect($summaries)->avg('Duration') * 1000)
            : 0;

        return [
            'total' => $total,
            'errors' => $errors,
            'avgDurationMs' => $avgDurationMs,
            'windowMinutes' => 60,
        ];
    }

    private function query(string $id, string $metricName, string $namespace, array $dimensions, string $stat): array
    {
        return [
            'Id' => $id,
            'MetricStat' => [
                'Metric' => [
                    'Namespace' => $namespace,
                    'MetricName' => $metricName,
                    'Dimensions' => $dimensions,
                ],
                'Period' => 3600,
                'Stat' => $stat,
            ],
            'ReturnData' => true,
        ];
    }

    /**
     * @param  array<string, float>  $divideBy  Client-side unit scaling per series id (CloudWatch returns raw units, e.g. bytes).
     */
    private function shapeTimeSeries($result, array $divideBy = []): array
    {
        $series = [];

        foreach ($result['MetricDataResults'] as $r) {
            $timestamps = $r['Timestamps'] ?? [];
            $values = $r['Values'] ?? [];
            $scale = $divideBy[$r['Id']] ?? 1;
            $points = [];

            foreach ($timestamps as $i => $ts) {
                $points[] = [
                    'timestamp' => $ts->getTimestamp(),
                    'value' => round(($values[$i] ?? 0) / $scale, 2),
                ];
            }

            usort($points, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

            $series[$r['Id']] = $points;
        }

        return $series;
    }

    private function rescue(callable $fn): ?array
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            Log::warning('Atlas WatchTower infra fetch failed: ' . $e->getMessage());

            return null;
        }
    }
}
