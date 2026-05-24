<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupVerify extends Command
{
    protected $signature   = 'backup:verify';
    protected $description = 'Verify that a database backup was uploaded to Google Drive within the last 25 hours';

    // A backup must have been uploaded within this window to be considered current
    const MAX_AGE_SECONDS = 25 * 3600;

    public function handle(): int
    {
        $credentialsPath = env('GOOGLE_DRIVE_CREDENTIALS');
        $folderId        = env('GOOGLE_DRIVE_FOLDER_ID');

        if (! $credentialsPath || ! $folderId || ! file_exists($credentialsPath)) {
            $this->warn('Google Drive credentials not configured — cannot verify backup.');
            Log::channel('security')->warning('Backup verification skipped: Drive credentials not configured');
            return self::FAILURE;
        }

        try {
            $client = new GoogleClient();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(GoogleDrive::DRIVE);

            $service  = new GoogleDrive($client);
            $response = $service->files->listFiles([
                'q'                         => "'{$folderId}' in parents and name contains 'backup_' and trashed = false",
                'fields'                    => 'files(id,name,createdTime)',
                'orderBy'                   => 'createdTime desc',
                'pageSize'                  => 1,
                'includeItemsFromAllDrives' => true,
                'supportsAllDrives'         => true,
            ]);

            $files = $response->getFiles();

            if (empty($files)) {
                $this->error('FAIL: No backup files found in Google Drive folder.');
                Log::channel('security')->error('Backup verification failed: no backups found in Drive');
                return self::FAILURE;
            }

            $latest      = $files[0];
            $createdTime = $latest->getCreatedTime(); // RFC 3339 UTC string
            $ageSeconds  = time() - (new \DateTimeImmutable($createdTime))->getTimestamp();
            $ageHours    = round($ageSeconds / 3600, 1);

            $this->line("Latest Drive backup: {$latest->getName()} (uploaded {$createdTime})");

            if ($ageSeconds > self::MAX_AGE_SECONDS) {
                $this->error("FAIL: Most recent backup is {$ageHours}h old — exceeds 25-hour threshold.");
                Log::channel('security')->error('Backup verification failed: backup too old', [
                    'file'        => $latest->getName(),
                    'age_hours'   => $ageHours,
                    'created_utc' => $createdTime,
                ]);
                return self::FAILURE;
            }

            $this->info("OK: Backup is current ({$ageHours}h old) — {$latest->getName()}");
            Log::info('Backup verification passed', [
                'file'      => $latest->getName(),
                'age_hours' => $ageHours,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Backup verification error: ' . $e->getMessage());
            Log::channel('security')->error('Backup verification error', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
