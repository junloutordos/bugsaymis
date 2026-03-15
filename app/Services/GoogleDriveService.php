<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Http\UploadedFile;

/**
 * Google Drive file upload service — uses a Shared Drive folder.
 *
 * WHY Shared Drive: Service accounts have no personal storage quota, so
 * they cannot upload to regular "My Drive" folders. Files must be placed
 * inside a Shared Drive where the service account is a member.
 *
 * Setup:
 *  1. composer require google/apiclient:^2.15
 *  2. Enable Drive API in Google Cloud Console for your project.
 *  3. Create a Service Account → download the JSON key.
 *  4. In Google Drive, create a Shared Drive.
 *  5. Add the service account email (from the JSON "client_email") as a
 *     Content Manager (or higher) of the Shared Drive.
 *  6. Copy the Shared Drive folder ID from the URL.
 *  7. Set in .env:
 *       GOOGLE_DRIVE_CREDENTIALS=/absolute/path/to/service-account.json
 *       GOOGLE_DRIVE_FOLDER_ID=<shared-drive-folder-id>
 */
class GoogleDriveService
{
    private Drive $drive;
    private ?string $folderId;

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(config('services.google_drive.credentials'));
        // Full Drive scope needed for Shared Drive membership
        $client->addScope(Drive::DRIVE);

        $this->drive    = new Drive($client);
        $this->folderId = config('services.google_drive.folder_id');
    }

    /**
     * Upload a file to the configured Shared Drive folder.
     *
     * @return array{ file_id: string, link: string }
     */
    public function upload(UploadedFile $file, string $fileName): array
    {
        $metadata = new DriveFile([
            'name'    => $fileName,
            'parents' => $this->folderId ? [$this->folderId] : [],
        ]);

        $uploaded = $this->drive->files->create($metadata, [
            'data'             => file_get_contents($file->getRealPath()),
            'mimeType'         => $file->getMimeType(),
            'uploadType'       => 'multipart',
            'fields'           => 'id',
            // Required for Shared Drive support
            'supportsAllDrives' => true,
        ]);

        $fileId = $uploaded->id;

        // Make the file publicly readable via link
        $this->drive->permissions->create(
            $fileId,
            new Permission(['type' => 'anyone', 'role' => 'reader']),
            ['supportsAllDrives' => true]
        );

        return [
            'file_id' => $fileId,
            'link'    => "https://drive.google.com/file/d/{$fileId}/view",
        ];
    }

    /**
     * Delete a file from Google Drive (Shared Drive aware).
     */
    public function delete(string $fileId): void
    {
        try {
            $this->drive->files->delete($fileId, ['supportsAllDrives' => true]);
        } catch (\Throwable) {
            // Silently ignore if already removed
        }
    }
}
