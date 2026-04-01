<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    // Command signature
    protected $signature = 'db:backup';

    // Command description
    protected $description = 'Backup the database and keep last 7 backups';

    public function handle()
    {
        // Get database credentials from config (not env() directly)
        $dbName = config('database.connections.mysql.database');
        $dbUser = config('database.connections.mysql.username');
        $dbPass = config('database.connections.mysql.password');
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = (string) config('database.connections.mysql.port', '3306');

        // Backup folder (outside web root, not publicly accessible)
        $backupPath = storage_path('app/db_backups/');
        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0750, true);
        }

        // Filename with timestamp
        $filename = $backupPath . 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';

        // Build command using escapeshellarg() on every argument to prevent injection
        // Password is passed via --defaults-extra-file to avoid it appearing in process list
        $cnfContent = "[client]\npassword=" . str_replace('"', '\\"', $dbPass) . "\nskip_ssl\n";
        $cnfFile    = tempnam(sys_get_temp_dir(), 'mysqldump_');
        file_put_contents($cnfFile, $cnfContent);
        chmod($cnfFile, 0600);

        $command = sprintf(
            'mysqldump --defaults-extra-file=%s -h %s -P %s -u %s %s > %s',
            escapeshellarg($cnfFile),
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($filename)
        );

        exec($command . ' 2>&1', $output, $return_var);

        // Always remove the temp credentials file
        @unlink($cnfFile);

        if ($return_var === 0) {
            $this->info("Backup created successfully: $filename");
            Log::info("Database backup created: $filename");
        } else {
            $this->error("Error creating backup!");
            Log::error("Database backup failed for $dbName at " . now()->toDateTimeString());
        }

        // Keep only last 7 backups
        $files = glob($backupPath . 'backup_*.sql');
        if (count($files) > 7) {
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            $oldFiles = array_slice($files, 0, count($files) - 7);
            foreach ($oldFiles as $file) {
                unlink($file);
                Log::info("Old backup deleted: $file");
            }
        }
    }
}
