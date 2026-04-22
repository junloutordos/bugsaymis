<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LogHealthCheck extends Command
{
    protected $signature   = 'log:health';
    protected $description = 'Check log file sizes and error spike indicators';

    // Alert thresholds
    const MAX_LOG_MB       = 50;   // single file alert threshold
    const MAX_LOGS_DIR_MB  = 500;  // total logs directory alert threshold
    const ERROR_SPIKE      = 100;  // errors in today's log triggering alert

    public function handle(): int
    {
        $logsPath = storage_path('logs');
        $issues   = [];

        // ── Total logs directory size ─────────────────────────────────────────
        $totalBytes = $this->dirSize($logsPath);
        $totalMb    = round($totalBytes / 1024 / 1024, 2);
        $this->line("Logs directory: {$totalMb} MB");

        if ($totalMb > self::MAX_LOGS_DIR_MB) {
            $issues[] = "Logs directory exceeds " . self::MAX_LOGS_DIR_MB . " MB ({$totalMb} MB)";
        }

        // ── Individual file sizes ─────────────────────────────────────────────
        foreach (glob($logsPath . '/*.log') as $file) {
            $mb   = round(filesize($file) / 1024 / 1024, 2);
            $name = basename($file);
            $this->line("  {$name}: {$mb} MB");

            if ($mb > self::MAX_LOG_MB) {
                $issues[] = "Log file {$name} exceeds " . self::MAX_LOG_MB . " MB ({$mb} MB)";
            }
        }

        // ── Error spike detection in today's daily log ────────────────────────
        $today    = date('Y-m-d');
        $todayLog = $logsPath . "/laravel-{$today}.log";

        if (file_exists($todayLog)) {
            $errorCount = 0;
            $handle     = fopen($todayLog, 'r');
            while (($line = fgets($handle)) !== false) {
                if (str_contains($line, '.ERROR:') || str_contains($line, '.CRITICAL:') || str_contains($line, '.EMERGENCY:')) {
                    $errorCount++;
                }
            }
            fclose($handle);

            $this->line("Today's error/critical entries: {$errorCount}");

            if ($errorCount > self::ERROR_SPIKE) {
                $issues[] = "Error spike detected: {$errorCount} error entries in today's log";
            }
        }

        // ── Disk space ────────────────────────────────────────────────────────
        $freeBytes  = disk_free_space($logsPath);
        $totalSpace = disk_total_space($logsPath);
        if ($totalSpace > 0) {
            $freePct = round(($freeBytes / $totalSpace) * 100, 1);
            $this->line("Disk free: {$freePct}%");
            if ($freePct < 10) {
                $issues[] = "Critical: only {$freePct}% disk space remaining";
            } elseif ($freePct < 20) {
                $issues[] = "Warning: disk space low ({$freePct}% free)";
            }
        }

        // ── Report ────────────────────────────────────────────────────────────
        if (empty($issues)) {
            $this->info('Log health: OK');
            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->warn($issue);
            Log::channel('security')->warning('[log:health] ' . $issue);
        }

        return self::FAILURE;
    }

    private function dirSize(string $path): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }
}
