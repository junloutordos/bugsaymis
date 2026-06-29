<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

class AtlasRequestStatsMiddleware
{
    // Per-request accumulators (reset on each PHP-FPM request cycle)
    private static bool  $capturing   = false;
    private static bool  $initialized = false;
    private static int   $queryCount  = 0;
    private static float $totalQueryMs = 0.0;
    private static int   $cacheHits   = 0;
    private static int   $cacheMisses = 0;

    public function handle(Request $request, Closure $next): Response
    {
        // Reset per-request state
        self::$capturing    = false;
        self::$initialized  = false;
        self::$queryCount   = 0;
        self::$totalQueryMs = 0.0;
        self::$cacheHits    = 0;
        self::$cacheMisses  = 0;

        // Only instrument authenticated web requests with named routes
        if ($request->user() && !self::$initialized) {
            self::$initialized = true;
            self::$capturing   = true;

            // Accumulate DB query timing in-memory (no per-query DB write)
            DB::listen(static function ($query): void {
                if (! self::$capturing) {
                    return;
                }
                self::$queryCount++;
                self::$totalQueryMs += (float) $query->time; // Laravel gives time in ms
            });

            // Accumulate cache hit/miss in-memory
            Event::listen(CacheHit::class, static function (): void {
                if (self::$capturing) {
                    self::$cacheHits++;
                }
            });
            Event::listen(CacheMissed::class, static function (): void {
                if (self::$capturing) {
                    self::$cacheMisses++;
                }
            });

            // Set session started_at once per new session (1 UPDATE, then never again)
            $this->initSessionStart($request);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Disable capturing before any of our own DB work
        self::$capturing = false;

        $routeName = $request->route()?->getName();
        if (! $routeName || ! $request->user()) {
            return;
        }

        $durationMs  = (int) ((microtime(true) - LARAVEL_START) * 1000);
        $isError     = $response->getStatusCode() >= 500;
        $hourBucket  = now()->startOfHour()->toDateTimeString();
        $route       = substr($routeName, 0, 200);
        $cacheHits   = self::$cacheHits;
        $cacheMisses = self::$cacheMisses;
        $queryCount  = self::$queryCount;
        $queryMs     = (int) self::$totalQueryMs;

        try {
            // Single UPSERT into hourly bucket — very low write volume
            DB::statement('
                INSERT INTO atlas_request_stats
                    (route_name, hour_bucket, request_count, error_count,
                     total_duration_ms, cache_hits, cache_misses, query_count, total_query_ms)
                VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    request_count     = request_count + 1,
                    error_count       = error_count     + VALUES(error_count),
                    total_duration_ms = total_duration_ms + VALUES(total_duration_ms),
                    cache_hits        = cache_hits      + VALUES(cache_hits),
                    cache_misses      = cache_misses    + VALUES(cache_misses),
                    query_count       = query_count     + VALUES(query_count),
                    total_query_ms    = total_query_ms  + VALUES(total_query_ms)
            ', [$route, $hourBucket, $isError ? 1 : 0, $durationMs, $cacheHits, $cacheMisses, $queryCount, $queryMs]);

            // Prune rows older than 7 days — runs on ~1% of requests to amortise cost
            if (random_int(1, 100) === 1) {
                DB::table('atlas_request_stats')
                    ->where('hour_bucket', '<', now()->subDays(7)->startOfHour())
                    ->delete();
            }
        } catch (\Throwable) {
            // Never let monitoring break the app
        }
    }

    private function initSessionStart(Request $request): void
    {
        // Guard: only run once per session lifetime
        if ($request->session()->has('_atlas_init')) {
            return;
        }

        try {
            DB::table('sessions')
                ->where('id', $request->session()->getId())
                ->whereNull('started_at')
                ->update(['started_at' => now()]);
        } catch (\Throwable) {
            // sessions.started_at column may not exist yet during migration window
        }

        $request->session()->put('_atlas_init', true);
    }
}
