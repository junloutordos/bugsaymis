<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks  = [];
        $healthy = true;

        // ── Database ─────────────────────────────────────────────────────────
        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'fail';
            $healthy = false;
        }

        // ── Redis ─────────────────────────────────────────────────────────────
        try {
            Cache::store('redis')->put('_health_ping', 1, 5);
            $checks['redis'] = 'ok';
        } catch (\Throwable $e) {
            $checks['redis'] = 'fail';
            $healthy = false;
        }

        // ── Queue (pending jobs check) ────────────────────────────────────────
        try {
            $failed = DB::table('failed_jobs')->count();
            $checks['failed_jobs'] = $failed;
            if ($failed > 50) {
                $checks['queue'] = 'degraded';
                $healthy = false;
            } else {
                $checks['queue'] = 'ok';
            }
        } catch (\Throwable $e) {
            $checks['queue'] = 'unknown';
        }

        // ── Disk space (storage/logs) ─────────────────────────────────────────
        $logsPath = storage_path('logs');
        $freeBytes = @disk_free_space($logsPath);
        $totalBytes = @disk_total_space($logsPath);
        if ($freeBytes !== false && $totalBytes > 0) {
            $freePercent = round(($freeBytes / $totalBytes) * 100, 1);
            $checks['disk_free_percent'] = $freePercent;
            if ($freePercent < 10) {
                $checks['disk'] = 'critical';
                $healthy = false;
            } elseif ($freePercent < 20) {
                $checks['disk'] = 'low';
            } else {
                $checks['disk'] = 'ok';
            }
        } else {
            $checks['disk'] = 'unknown';
        }

        $status = $healthy ? 200 : 503;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }
}
