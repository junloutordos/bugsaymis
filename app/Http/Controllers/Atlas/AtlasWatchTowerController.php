<?php

namespace App\Http\Controllers\Atlas;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Atlas\AtlasWatchTowerInfraService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;

class AtlasWatchTowerController extends Controller
{
    /**
     * How far back (seconds) to look for the "recent" lists on the dashboard.
     */
    private const WINDOW_SECONDS = 24 * 60 * 60;

    public function __construct(
        private readonly AtlasWatchTowerInfraService $infra,
    ) {}

    public function index()
    {
        $since = now()->subSeconds(self::WINDOW_SECONDS)->getTimestamp();

        return Inertia::render('Atlas/WatchTower', [
            'enabled' => config('pulse.enabled'),
            'windowHours' => 24,
            'infra' => $this->infra->getInfraSummary(),
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

    public function activeUsers()
    {
        $data = Cache::remember('atlas.active_users', 30, function () {
            $fiveMinAgo   = now()->subMinutes(5)->timestamp;
            $thirtyMinAgo = now()->subMinutes(30)->timestamp;
            $todayStart   = today('Asia/Manila')->startOfDay()->timestamp;

            try {
                // Redis sorted set: member=user_id, score=last_activity unix timestamp.
                // Sessions table is not used here because prod runs SESSION_DRIVER=redis.
                $recentIds = Redis::zrangebyscore('atlas:active_users', $thirtyMinAgo, '+inf');
                $todayIds  = Redis::zrangebyscore('atlas:active_users', $todayStart, '+inf');

                $users = [];
                if (! empty($recentIds)) {
                    $userRows = User::whereIn('id', $recentIds)
                        ->where('status', '<>', 'inactive')
                        ->get(['id', 'name'])
                        ->keyBy('id');

                    foreach ($recentIds as $userId) {
                        if (! isset($userRows[$userId])) {
                            continue;
                        }
                        $meta         = Redis::hgetall("atlas:user_meta:{$userId}");
                        $lastActivity = (int) ($meta['last_activity'] ?? 0);
                        $user         = $userRows[$userId];
                        $users[]      = [
                            'id'            => $user->id,
                            'name'          => $user->name,
                            'initials'      => $this->initials($user->name),
                            'last_activity' => $lastActivity,
                            'last_seen_at'  => $lastActivity ? date('Y-m-d\TH:i:s\Z', $lastActivity) : null,
                            'ip_address'    => $meta['ip_address'] ?? null,
                            'browser'       => $this->parseBrowser($meta['user_agent'] ?? ''),
                            'is_online'     => $lastActivity >= $fiveMinAgo,
                        ];
                    }

                    usort($users, fn ($a, $b) => $b['last_activity'] <=> $a['last_activity']);
                }

                $onlineNow  = collect($users)->where('is_online', true)->count();
                $dailyTotal = count(array_unique($todayIds));

            } catch (\Throwable) {
                // Fallback to sessions table (works in dev with database session driver)
                $sessions = DB::table('sessions')
                    ->join('users', 'sessions.user_id', '=', 'users.id')
                    ->where('sessions.last_activity', '>=', $thirtyMinAgo)
                    ->whereNotNull('sessions.user_id')
                    ->where('users.status', '<>', 'inactive')
                    ->orderByDesc('sessions.last_activity')
                    ->get(['users.id', 'users.name', 'sessions.ip_address', 'sessions.user_agent', 'sessions.last_activity']);

                $users = $sessions->unique('id')->map(fn ($s) => [
                    'id'            => $s->id,
                    'name'          => $s->name,
                    'initials'      => $this->initials($s->name),
                    'last_activity' => $s->last_activity,
                    'last_seen_at'  => date('Y-m-d\TH:i:s\Z', $s->last_activity),
                    'ip_address'    => $s->ip_address,
                    'browser'       => $this->parseBrowser($s->user_agent ?? ''),
                    'is_online'     => $s->last_activity >= $fiveMinAgo,
                ])->values()->toArray();

                $onlineNow  = collect($users)->where('is_online', true)->count();
                $dailyTotal = DB::table('sessions')
                    ->where('last_activity', '>=', $todayStart)
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id');
            }

            return [
                'users'        => $users,
                'counts'       => [
                    'now'    => $onlineNow,
                    'recent' => count($users),
                    'today'  => $dailyTotal,
                ],
                'refreshed_at' => now()->toISOString(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Last ~15 audit-log actions for one active user, for the expandable
     * panel in the Active Users card. Notification rows are excluded — a
     * user's request creates notifications addressed to other people, which
     * would pollute their own trail.
     */
    public function userActivity(User $user)
    {
        $data = Cache::remember("atlas.user_activity.{$user->id}", 30, function () use ($user) {
            return DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNull('auditable_type')
                        ->orWhere('auditable_type', '<>', \Illuminate\Notifications\DatabaseNotification::class);
                })
                ->orderByDesc('id')
                ->limit(15)
                ->get(['action', 'auditable_type', 'auditable_id', 'url', 'created_at'])
                ->map(fn ($row) => [
                    'action'     => $row->action,
                    'subject'    => $this->subjectLabel($row->auditable_type),
                    'subject_id' => $row->auditable_id,
                    'path'       => $row->url ? (parse_url($row->url, PHP_URL_PATH) ?: null) : null,
                    'time'       => Carbon::parse($row->created_at)->toISOString(),
                ])
                ->all();
        });

        return response()->json(['activities' => $data]);
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }
        return strtoupper(mb_substr($name, 0, 2));
    }

    private function parseBrowser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg')     => 'Edge',
            str_contains($ua, 'Chrome')  => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari')  => 'Safari',
            default                       => 'Other',
        };
    }

    /**
     * "App\Models\HR\LeaveApplication" → "Leave Application".
     */
    private function subjectLabel(?string $class): ?string
    {
        if (! $class) {
            return null;
        }
        return trim(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($class)));
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
        // Pulse stores count-aggregate totals in `value` (`count` is only
        // populated for avg aggregates) and writes every event into four
        // period buckets (60/360/1440/10080) — sum a single period matching
        // the 24h window or the totals quadruple.
        return DB::table('pulse_aggregates')
            ->select('type', DB::raw('SUM(value) as total'))
            ->whereIn('type', ['queued', 'processing', 'processed', 'released', 'failed'])
            ->where('aggregate', 'count')
            ->where('period', 1440)
            ->where('bucket', '>=', $since)
            ->groupBy('type')
            ->pluck('total', 'type')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
