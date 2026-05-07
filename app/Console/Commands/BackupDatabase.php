<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;

class BackupDatabase extends Command
{
    protected $signature   = 'db:backup';
    protected $description = 'Backup the database, keep last 7 local copies, and upload to Google Drive';

    // How many backups to retain on Google Drive
    const DRIVE_RETENTION = 30;

    public function handle(): int
    {
        // The raw SQL dump can exceed 40MB. Raise the limit so gzip + Drive upload succeed.
        ini_set('memory_limit', '512M');

        $dbName = config('database.connections.mysql.database');
        $dbUser = env('DB_BACKUP_USERNAME', config('database.connections.mysql.username'));
        $dbPass = env('DB_BACKUP_PASSWORD', config('database.connections.mysql.password'));
        $dbHost = config('database.connections.mysql.host', '127.0.0.1');
        $dbPort = (string) config('database.connections.mysql.port', '3306');

        // ── Local backup directory ────────────────────────────────────────────
        $backupPath = storage_path('app/db_backups/');
        if (! file_exists($backupPath)) {
            mkdir($backupPath, 0750, true);
        }

        $basename     = 'backup_' . now()->format('Y-m-d_H-i-s') . '.sql';
        $basenamegz   = $basename . '.gz';
        $filename     = $backupPath . $basename;
        $filenamegz   = $backupPath . $basenamegz;

        // ── Run mysqldump ─────────────────────────────────────────────────────
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

        exec($command . ' 2>&1', $output, $returnVar);
        @unlink($cnfFile);

        if ($returnVar !== 0) {
            $this->error('Backup failed: ' . implode(' ', $output));
            Log::channel('security')->error('Database backup failed', [
                'db'     => $dbName,
                'output' => implode(' ', $output),
            ]);
            return self::FAILURE;
        }

        // ── Gzip the dump to cut size ~10x before Drive upload ───────────────
        exec("gzip -f " . escapeshellarg($filename), $gzOut, $gzCode);
        $uploadFile   = ($gzCode === 0 && file_exists($filenamegz)) ? $filenamegz : $filename;
        $uploadBase   = ($gzCode === 0) ? $basenamegz : $basename;

        $sizeMb = round(filesize($uploadFile) / 1024 / 1024, 2);
        $this->info("Local backup created: {$uploadBase} ({$sizeMb} MB)");
        Log::info("Database backup created: {$uploadFile} ({$sizeMb} MB)");

        // ── Prune local copies (keep last 7) ──────────────────────────────────
        $files = glob($backupPath . 'backup_*.sql*');
        if (count($files) > 7) {
            usort($files, fn ($a, $b) => filemtime($a) - filemtime($b));
            foreach (array_slice($files, 0, count($files) - 7) as $old) {
                unlink($old);
                Log::info("Local backup pruned: {$old}");
            }
        }

        // ── Upload to Google Drive ────────────────────────────────────────────
        $credentialsPath = env('GOOGLE_DRIVE_CREDENTIALS');
        $folderId        = env('GOOGLE_DRIVE_FOLDER_ID');

        if (! $credentialsPath || ! $folderId || ! file_exists($credentialsPath)) {
            $this->warn('Google Drive credentials not configured — skipping offsite upload.');
            return self::SUCCESS;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(GoogleDrive::DRIVE); // Full scope required for Shared Drive folders

            $service  = new GoogleDrive($client);
            $metadata = new DriveFile([
                'name'    => $uploadBase,
                'parents' => [$folderId],
            ]);

            $service->files->create($metadata, [
                'data'       => file_get_contents($uploadFile),
                'mimeType'   => 'application/gzip',
                'uploadType' => 'multipart',
                'fields'     => 'id,name',
                'supportsAllDrives' => true,
            ]);

            $this->info("Uploaded to Google Drive: {$uploadBase}");
            Log::info("Backup uploaded to Google Drive: {$basename}");

            // ── Prune old Drive backups (keep last DRIVE_RETENTION) ───────────
            $this->pruneGoogleDriveBackups($service, $folderId);

        } catch (\Throwable $e) {
            // Offsite upload failure is non-fatal — local backup still exists
            $this->warn('Google Drive upload failed: ' . $e->getMessage());
            Log::channel('security')->warning('Backup Drive upload failed', ['error' => $e->getMessage()]);
        }

        return self::SUCCESS;
    }

    /**
     * Delete the oldest backups in the Drive folder, retaining the most recent N.
     */
    private function pruneGoogleDriveBackups(GoogleDrive $service, string $folderId): void
    {
        $response = $service->files->listFiles([
            'q'       => "'{$folderId}' in parents and name contains 'backup_' and trashed = false",
            'fields'  => 'files(id,name,createdTime)',
            'orderBy' => 'createdTime',
        ]);

        $files = $response->getFiles();

        if (count($files) <= self::DRIVE_RETENTION) {
            return;
        }

        $toDelete = array_slice($files, 0, count($files) - self::DRIVE_RETENTION);
        foreach ($toDelete as $file) {
            try {
                $service->files->delete($file->getId());
                $this->line("Drive backup pruned: {$file->getName()}");
                Log::info("Drive backup pruned: {$file->getName()}");
            } catch (\Throwable $e) {
                Log::warning("Failed to prune Drive backup {$file->getName()}: " . $e->getMessage());
            }
        }
    }
}
