<?php

namespace App\Services\FacultyLoading;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchSubmissionFileService
{
    public const MAX_BYTES = 10 * 1024 * 1024; // 10MB, matches Chat module's cap

    // Same whitelist as ChatController — deliberately excludes svg/html/executables.
    public const ALLOWED_MIMES = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/zip' => 'zip',
    ];

    /**
     * @return array{s3_key: string, mime_type: string, size_bytes: int, original_filename: string}
     */
    public function decodeAndStore(string $dataUri, string $originalName, ?string $acceptedTypesCsv = null): array
    {
        if (! preg_match('/^data:([^;]+);base64,(.+)$/', $dataUri, $m)) {
            throw ValidationException::withMessages(['file' => 'Invalid file format.']);
        }

        $mime = strtolower(trim($m[1]));
        if (! isset(self::ALLOWED_MIMES[$mime])) {
            throw ValidationException::withMessages(['file' => 'That file type is not supported.']);
        }

        $ext = self::ALLOWED_MIMES[$mime];

        if ($acceptedTypesCsv) {
            $allowed = array_map('trim', explode(',', strtolower($acceptedTypesCsv)));
            if (! in_array($ext, $allowed, true)) {
                throw ValidationException::withMessages(['file' => "This requirement only accepts: {$acceptedTypesCsv}."]);
            }
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            throw ValidationException::withMessages(['file' => 'Invalid file data.']);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Files must be 10MB or smaller.']);
        }

        $s3Key = 'research-requirements/' . Str::uuid() . '.' . $ext;
        Storage::disk('s3')->put($s3Key, $binary);

        return [
            's3_key'             => $s3Key,
            'mime_type'          => $mime,
            'size_bytes'         => strlen($binary),
            'original_filename'  => $originalName,
        ];
    }

    public function encodeKey(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public function decodeKey(string $fileId): ?string
    {
        if (! str_starts_with($fileId, 's3.')) {
            return null;
        }
        $padded = strtr(substr($fileId, 3), '-_', '+/');
        $pad    = strlen($padded) % 4;
        if ($pad) $padded .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode($padded, true);
        return ($decoded !== false) ? $decoded : null;
    }
}
