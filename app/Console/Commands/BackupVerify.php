<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupVerify extends Command
{
    protected $signature   = 'backup:verify {--file= : Specific .sql file to verify (defaults to latest)}';
    protected $description = 'Verify the integrity of the most recent database backup';

    // Minimum acceptable backup size (bytes) — anything smaller is almost certainly corrupt/empty
    const MIN_SIZE_BYTES = 10_000;

    // SQL structure markers that must be present in a valid mysqldump file
    const REQUIRED_MARKERS = [
        'MySQL dump',
        'CREATE TABLE',
        'INSERT INTO',
    ];

    public function handle(): int
    {
        $backupPath = storage_path('app/db_backups/');
        $target     = $this->option('file');

        if ($target) {
            $file = str_starts_with($target, '/') ? $target : $backupPath . $target;
        } else {
            // Latest backup by mtime
            $files = glob($backupPath . 'backup_*.sql');
            if (empty($files)) {
                $this->error('No backup files found in ' . $backupPath);
                return self::FAILURE;
            }
            usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));
            $file = $files[0];
        }

        $basename = basename($file);

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            Log::channel('security')->error("Backup verification failed: file not found", ['file' => $basename]);
            return self::FAILURE;
        }

        // ── Size check ────────────────────────────────────────────────────────
        $size = filesize($file);
        $sizeMb = round($size / 1024 / 1024, 2);
        $this->line("Verifying: {$basename} ({$sizeMb} MB)");

        if ($size < self::MIN_SIZE_BYTES) {
            $this->error("FAIL: File is suspiciously small ({$size} bytes). May be corrupt or empty.");
            Log::channel('security')->error("Backup verification failed: file too small", [
                'file'  => $basename,
                'bytes' => $size,
            ]);
            return self::FAILURE;
        }

        // ── SQL structure markers ─────────────────────────────────────────────
        $content = file_get_contents($file, false, null, 0, 65536); // read first 64 KB
        $missing = [];

        foreach (self::REQUIRED_MARKERS as $marker) {
            if (! str_contains($content, $marker)) {
                // Some smaller dumps may have no INSERT INTO yet — check full file for INSERT
                if ($marker === 'INSERT INTO') {
                    $full = file_get_contents($file);
                    if (! str_contains($full, 'INSERT INTO')) {
                        $missing[] = $marker;
                    }
                } else {
                    $missing[] = $marker;
                }
            }
        }

        if (! empty($missing)) {
            $this->error('FAIL: Missing expected SQL markers: ' . implode(', ', $missing));
            Log::channel('security')->error("Backup verification failed: missing SQL markers", [
                'file'    => $basename,
                'missing' => $missing,
            ]);
            return self::FAILURE;
        }

        // ── Line count sanity check ───────────────────────────────────────────
        $lineCount = substr_count(file_get_contents($file), "\n");
        $this->line("  Lines: {$lineCount}");

        if ($lineCount < 50) {
            $this->warn("WARNING: Only {$lineCount} lines — backup may be incomplete.");
            Log::channel('security')->warning("Backup verification warning: low line count", [
                'file'  => $basename,
                'lines' => $lineCount,
            ]);
        }

        $this->info("OK: {$basename} passed integrity checks ({$sizeMb} MB, {$lineCount} lines)");
        Log::info("Backup verified OK: {$basename}", ['mb' => $sizeMb, 'lines' => $lineCount]);

        return self::SUCCESS;
    }
}
