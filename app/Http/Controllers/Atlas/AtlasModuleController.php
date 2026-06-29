<?php

namespace App\Http\Controllers\Atlas;

use App\Http\Controllers\Controller;
use App\Models\Atlas\AtlasModuleSetting;
use App\Models\User;
use App\Services\Atlas\AtlasMaturityService;
use App\Services\Atlas\AtlasMetricsService;
use App\Services\Atlas\AtlasModuleHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;

class AtlasModuleController extends Controller
{
    public function __construct(
        private readonly AtlasModuleHealthService $health,
        private readonly AtlasMetricsService      $metrics,
        private readonly AtlasMaturityService     $maturity,
    ) {}

    public function index()
    {
        $modules = $this->health->getAllModuleHealth();

        // Load stored overall maturity scores keyed by module_key for card display
        $storedScores = \App\Models\Atlas\AtlasModuleMaturityScore::selectRaw(
            'module_key, AVG(score) as avg_score'
        )->groupBy('module_key')->pluck('avg_score', 'module_key')->toArray();

        $modulesWithMaturity = array_map(function ($m) use ($storedScores) {
            $m['maturity_overall'] = isset($storedScores[$m['key']])
                ? round($storedScores[$m['key']], 1)
                : null;
            return $m;
        }, $modules);

        return Inertia::render('Atlas/ModulesDashboard', [
            'modules'            => $modulesWithMaturity,
            'summary'            => $this->health->getSummary($modules),
            'integrations'       => $this->health->getIntegrationStatus(),
            'categoryLabels'     => config('atlas-modules.category_labels'),
            'maturityLabels'     => config('atlas-modules.maturity_labels'),
            'maturityDimensions' => config('atlas-modules.maturity_dimensions'),
            'cachedAt'           => now('Asia/Manila')->toISOString(),
        ]);
    }

    public function refresh()
    {
        $this->health->clearCache();
        $modules = $this->health->getAllModuleHealth();

        $storedScores = \App\Models\Atlas\AtlasModuleMaturityScore::selectRaw(
            'module_key, AVG(score) as avg_score'
        )->groupBy('module_key')->pluck('avg_score', 'module_key')->toArray();

        $modulesWithMaturity = array_map(function ($m) use ($storedScores) {
            $m['maturity_overall'] = isset($storedScores[$m['key']])
                ? round($storedScores[$m['key']], 1)
                : null;
            return $m;
        }, $modules);

        return response()->json([
            'modules'      => $modulesWithMaturity,
            'summary'      => $this->health->getSummary($modules),
            'integrations' => $this->health->getIntegrationStatus(),
            'cachedAt'     => now('Asia/Manila')->toISOString(),
        ]);
    }

    public function moduleMetrics(string $key)
    {
        $modules = $this->health->getAllModuleHealth();
        $module  = collect($modules)->firstWhere('key', $key);

        if (! $module) {
            return response()->json(['error' => 'Module not found'], 404);
        }

        $metrics  = $this->metrics->getModuleMetrics($module);
        $radar    = $this->maturity->getRadarData($key);
        $scores   = $this->maturity->getScores($key);
        $overall  = $this->maturity->getOverallScore($key);
        $settings = AtlasModuleSetting::with('owner:id,name')->find($key)
            ?? new AtlasModuleSetting(['module_key' => $key, 'version' => '1.0.0']);

        $users = User::where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'module'     => $module,
            'metrics'    => $metrics,
            'maturity'   => ['radar' => $radar, 'scores' => $scores, 'overall' => $overall],
            'settings'   => $settings,
            'dimensions' => config('atlas-modules.maturity_dimensions'),
            'users'      => $users,
        ]);
    }

    public function saveMaturity(Request $request, string $key)
    {
        $validated = $request->validate([
            'dimension'         => 'required|string|in:functional,technical,security,ai_readiness,data_quality,ux,governance',
            'criteria_checks'   => 'required|array',
            'criteria_checks.*' => 'boolean',
            'notes'             => 'nullable|string|max:500',
        ]);

        $score = $this->maturity->upsertScore(
            $key,
            $validated['dimension'],
            $validated['criteria_checks'],
            $validated['notes'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'score'   => $score,
            'radar'   => $this->maturity->getRadarData($key),
            'overall' => $this->maturity->getOverallScore($key),
        ]);
    }

    public function saveSettings(Request $request, string $key)
    {
        $validated = $request->validate([
            'owner_id'  => 'nullable|exists:users,id',
            'sla_hours' => 'nullable|integer|min:1|max:8760',
            'version'   => 'nullable|string|max:20',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $settings = AtlasModuleSetting::updateOrCreate(
            ['module_key' => $key],
            $validated
        );

        $this->metrics->clearModuleCache($key);

        return response()->json([
            'settings' => $settings->load('owner:id,name'),
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
}
