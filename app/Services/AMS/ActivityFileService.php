<?php

namespace App\Services\AMS;

use Illuminate\Support\Facades\Storage;

/**
 * Handles base64 → S3 upload for AMS Activity attachments (banner, special order,
 * activity report, official documentation). Mirrors WFHService's base64/S3 proxy
 * convention: the DB column stores an encoded "s3.<base64url>" file_id, distinguishing
 * new S3-backed uploads from legacy Storage::disk('public') paths already in prod.
 */
class ActivityFileService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg'      => 'jpg',
        'image/jpg'       => 'jpg',
        'image/png'       => 'png',
        'application/pdf' => 'pdf',
    ];

    /**
     * Decode a base64 data URI and upload it to S3 under AMS/{activityId}/{field}/...
     * Returns the encoded file_id to store directly in the activity's file column.
     */
    public function uploadBase64File(int $activityId, string $field, string $dataUri): string
    {
        if (str_contains($dataUri, ',')) {
            [$meta, $base64] = explode(',', $dataUri, 2);
        } else {
            $meta = null;
            $base64 = $dataUri;
        }

        $mime = 'application/octet-stream';
        if ($meta && preg_match('/^data:([^;]+);base64$/', $meta, $m)) {
            $mime = $m[1];
        }
        $ext = self::MIME_EXTENSIONS[$mime] ?? 'bin';

        $s3Key = "AMS/{$activityId}/{$field}/" . uniqid('', true) . ".{$ext}";
        Storage::disk('s3')->put($s3Key, base64_decode($base64));

        return $this->encodeS3Key($s3Key);
    }

    /**
     * Delete a stored file, whether it's a new encoded S3 file_id or a legacy
     * Storage::disk('public') path.
     */
    public function delete(?string $storedValue): void
    {
        if (! $storedValue) {
            return;
        }

        if (str_starts_with($storedValue, 's3.')) {
            $s3Key = $this->decodeS3Key($storedValue);
            if ($s3Key) {
                Storage::disk('s3')->delete($s3Key);
            }
            return;
        }

        Storage::disk('public')->delete($storedValue);
    }

    public function encodeS3Key(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public function decodeS3Key(string $fileId): ?string
    {
        if (! str_starts_with($fileId, 's3.')) {
            return null;
        }

        $padded = strtr(substr($fileId, 3), '-_', '+/');
        $pad    = strlen($padded) % 4;
        if ($pad) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($padded, true);

        return $decoded !== false ? $decoded : null;
    }
}
