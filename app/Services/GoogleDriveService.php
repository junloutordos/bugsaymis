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
    private ?Drive $drive = null;
    private ?Client $client = null;
    private ?string $folderId;

    public function __construct()
    {
        $credentials = config('services.google_drive.credentials');
        $this->folderId = config('services.google_drive.folder_id');

        if (! $credentials) {
            return; // credentials not configured — Drive calls will throw when invoked
        }

        try {
            $client = new Client();
            $client->setAuthConfig($credentials);
            // Full Drive scope needed for Shared Drive membership
            $client->addScope(Drive::DRIVE);
            $this->client = $client;
            $this->drive = new Drive($client);
        } catch (\Throwable $e) {
            logger()->warning('GoogleDriveService: failed to initialize client', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getDrive(): Drive
    {
        if ($this->drive === null) {
            throw new \RuntimeException('Google Drive credentials are not configured (GOOGLE_DRIVE_CREDENTIALS).');
        }
        return $this->drive;
    }

    /**
     * Upload raw bytes to a specific folder (base64-decoded content, Cloudflare-safe path).
     * Pass null for $folderId to use the instance default.
     *
     * @return array{ file_id: string, link: string }
     */
    public function uploadRaw(string $bytes, string $fileName, string $mimeType, ?string $folderId = null): array
    {
        $folder = $folderId ?? $this->folderId;

        $metadata = new DriveFile([
            'name'    => $fileName,
            'parents' => $folder ? [$folder] : [],
        ]);

        $uploaded = $this->getDrive()->files->create($metadata, [
            'data'              => $bytes,
            'mimeType'          => $mimeType,
            'uploadType'        => 'multipart',
            'fields'            => 'id',
            'supportsAllDrives' => true,
        ]);

        $fileId = $uploaded->id;

        $this->getDrive()->permissions->create(
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

        $uploaded = $this->getDrive()->files->create($metadata, [
            'data'             => file_get_contents($file->getRealPath()),
            'mimeType'         => $file->getMimeType(),
            'uploadType'       => 'multipart',
            'fields'           => 'id',
            // Required for Shared Drive support
            'supportsAllDrives' => true,
        ]);

        $fileId = $uploaded->id;

        // Make the file publicly readable via link
        $this->getDrive()->permissions->create(
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
     * Download a file's raw content from Google Drive using the service account.
     * Returns ['content' => string, 'mimeType' => string].
     */
    public function download(string $fileId): array
    {
        $response = $this->getDrive()->files->get($fileId, [
            'alt'               => 'media',
            'supportsAllDrives' => true,
        ]);

        return [
            'content'  => $response->getBody()->getContents(),
            'mimeType' => $response->getHeaderLine('Content-Type') ?: 'image/jpeg',
        ];
    }

    /**
     * Delete a file from Google Drive (Shared Drive aware).
     */
    public function delete(string $fileId): void
    {
        try {
            $this->getDrive()->files->delete($fileId, ['supportsAllDrives' => true]);
        } catch (\Throwable) {
            // Silently ignore if already removed
        }
    }

    /**
     * Find a child folder by name under a parent, creating it if missing.
     * Returns the folder ID. Used by device backups to mirror local paths —
     * no public permission is ever added to these folders.
     */
    public function ensureFolder(string $name, string $parentId): string
    {
        $escaped = addcslashes($name, "\\'");

        $existing = $this->getDrive()->files->listFiles([
            'q' => "name = '{$escaped}' and '{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
            'pageSize' => 1,
            'fields' => 'files(id)',
        ]);

        if (count($existing->getFiles()) > 0) {
            return $existing->getFiles()[0]->getId();
        }

        $folder = $this->getDrive()->files->create(new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]), [
            'supportsAllDrives' => true,
            'fields' => 'id',
        ]);

        return $folder->id;
    }

    /**
     * Start a resumable upload session for a NEW file and return the session
     * URI. The caller (an enrolled Sentinel device) PUTs the bytes straight
     * to googleapis.com — file content never transits this server, and the
     * service-account credentials never leave it. No public permission is
     * added: backup files stay private to Shared Drive members.
     */
    public function createResumableSession(string $fileName, string $mimeType, int $sizeBytes, string $parentId): string
    {
        return $this->requestSessionUri(
            'POST',
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true',
            ['name' => $fileName, 'parents' => [$parentId]],
            $mimeType,
            $sizeBytes,
        );
    }

    /**
     * Start a resumable upload session that replaces the content of an
     * EXISTING file (Drive keeps the old content as a revision).
     */
    public function createResumableUpdateSession(string $fileId, string $mimeType, int $sizeBytes): string
    {
        return $this->requestSessionUri(
            'PATCH',
            "https://www.googleapis.com/upload/drive/v3/files/{$fileId}?uploadType=resumable&supportsAllDrives=true",
            [],
            $mimeType,
            $sizeBytes,
        );
    }

    private function requestSessionUri(string $method, string $url, array $metadata, string $mimeType, int $sizeBytes): string
    {
        $response = \Illuminate\Support\Facades\Http::withToken($this->accessToken())
            ->withHeaders([
                'X-Upload-Content-Type' => $mimeType,
                'X-Upload-Content-Length' => (string) $sizeBytes,
            ])
            ->send($method, $url, ['json' => $metadata ?: (object) []]);

        if ($response->status() === 404) {
            throw new DriveFileGoneException('Drive file no longer exists.');
        }

        $response->throw();

        $sessionUri = $response->header('Location');
        if (! $sessionUri) {
            throw new \RuntimeException('Drive did not return a resumable session URI.');
        }

        return $sessionUri;
    }

    private function accessToken(): string
    {
        $this->getDrive(); // throws if credentials are not configured

        if (! $this->client->getAccessToken() || $this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithAssertion();
        }

        $token = $this->client->getAccessToken()['access_token'] ?? null;
        if (! $token) {
            throw new \RuntimeException('Could not obtain a Google Drive access token.');
        }

        return $token;
    }
}

/**
 * The Drive file backing a resumable update session has been deleted —
 * callers should fall back to creating the file fresh.
 */
class DriveFileGoneException extends \RuntimeException
{
}
