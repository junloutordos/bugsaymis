<?php

namespace App\Http\Controllers\Atlas;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AtlasWatchTowerController extends Controller
{
    /**
     * How far back (seconds) to look for the "recent" lists on the dashboard.
     */
    private const WINDOW_SECONDS = 24 * 60 * 60;

    public function index()
    {
        $since = now()->subSeconds(self::WINDOW_SECONDS)->getTimestamp();

        return Inertia::render('Atlas/WatchTower', [
            'enabled' => config('pulse.enabled'),
            'windowHours' => 24,
            'slowRequests' => $this->recentEntries('slow_request', $since, function (array $key, int $value) {
                [$method, $path, $via] = array_pad($key, 3, null);

                return [
                    'method' => $method,
                    'path' => $path,
                    'via' => $via,
                    'duration_ms' => $value,
                ];
            }),
            'exceptions' => $this->recentEntries('exception', $since, function (array $key) {
                [$class, $location] = array_pad($key, 2, null);

                return [
                    'class' => $class,
                    'location' => $location,
                ];
            }),
            'slowQueries' => $this->recentEntries('slow_query', $since, function (array $key, int $value) {
                [$sql, $location] = array_pad($key, 2, null);

                return [
                    'sql' => $sql,
                    'location' => $location,
                    'duration_ms' => $value,
                ];
            }),
            'queueThroughput' => $this->queueThroughput($since),
        ]);
    }

    /**
     * Fetch the most recent raw entries for a Pulse recorder type and decode
     * their JSON-encoded key into a display-friendly shape.
     */
    private function recentEntries(string $type, int $since, callable $shape): array
    {
        return DB::table('pulse_entries')
            ->where('type', $type)
            ->where('timestamp', '>=', $since)
            ->orderByDesc('timestamp')
            ->limit(25)
            ->get()
            ->map(function ($row) use ($shape) {
                $key = json_decode($row->key, true) ?? [];

                return array_merge($shape($key, (int) $row->value), [
                    'timestamp' => (int) $row->timestamp,
                ]);
            })
            ->all();
    }

    /**
     * Sum queue event counts (queued/processing/processed/released/failed)
     * from the bucketed aggregates table over the window.
     */
    private function queueThroughput(int $since): array
    {
        return DB::table('pulse_aggregates')
            ->select('type', DB::raw('SUM(count) as total'))
            ->whereIn('type', ['queued', 'processing', 'processed', 'released', 'failed'])
            ->where('bucket', '>=', $since)
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
