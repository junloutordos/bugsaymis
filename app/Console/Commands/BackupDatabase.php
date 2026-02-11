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
        // Get database credentials from .env
        $dbName = env('DB_DATABASE');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');
        $dbHost = env('DB_HOST', '127.0.0.1');

        // Backup folder
        $backupPath = storage_path('app/db_backups/');
        if (!file_exists($backupPath)) mkdir($backupPath, 0777, true);

        // Filename with timestamp
        $filename = $backupPath . 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';

        // mysqldump command
       $mysqldumpPath = '"C:\xampp\mysql\bin\mysqldump.exe"'; // put quotes for Windows paths with spaces
        $command = "$mysqldumpPath -h $dbHost -u $dbUser " . ($dbPass ? "-p$dbPass" : "") . " $dbName > \"$filename\"";


        // Execute backup
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $this->info("Backup created successfully: $filename");
            Log::info("Database backup created: $filename");
        } else {
            $this->error("Error creating backup!");
            Log::error("Database backup failed for $dbName at " . now()->toDateTimeString());
        }

        // Keep only last 7 backups
        $files = glob($backupPath . 'backup_*.sql');
        if (count($files) > 49) {
            usort($files, fn($a, $b) => filemtime($a) - filemtime($b));
            $oldFiles = array_slice($files, 0, count($files) - 7);
            foreach ($oldFiles as $file) {
                unlink($file);
                Log::info("Old backup deleted: $file");
            }
        }
    }
}
