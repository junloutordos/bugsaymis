<?php

namespace App\Services\ALP;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AlpFileService
{
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function uploadDataUri(string $folder, string $dataUri, int $maxBytes = 10_000_000): string
    {
        if (! preg_match('/^data:([^;]+);base64,(.+)$/s', $dataUri, $matches)) {
            throw ValidationException::withMessages(['file_base64' => 'The uploaded file must be a base64 data URI.']);
        }

        $mime = strtolower($matches[1]);
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;
        $contents = base64_decode($matches[2], true);

        if (! $extension || $contents === false) {
            throw ValidationException::withMessages(['file_base64' => 'Only PDF, JPEG, and PNG files are allowed.']);
        }
        $imageInfo = in_array($mime, ['image/jpeg', 'image/png'], true) ? @getimagesizefromstring($contents) : false;
        $imageType = is_array($imageInfo) ? ($imageInfo[2] ?? null) : null;
        $isValidContent = match ($mime) {
            'application/pdf' => str_starts_with($contents, '%PDF-'),
            'image/jpeg' => $imageType === IMAGETYPE_JPEG,
            'image/png' => $imageType === IMAGETYPE_PNG,
            default => false,
        };
        if (! $isValidContent) {
            throw ValidationException::withMessages(['file_base64' => 'The file content does not match its declared type.']);
        }
        if (strlen($contents) > $maxBytes) {
            throw ValidationException::withMessages(['file_base64' => 'The uploaded file is too large.']);
        }

        $key = 'ALP/'.trim($folder, '/').'/'.uniqid('', true).'.'.$extension;
        Storage::disk('s3')->put($key, $contents, ['ContentType' => $mime]);

        return $this->encode($key);
    }

    public function storeBytes(string $folder, string $filename, string $contents, string $mime = 'application/pdf'): string
    {
        $key = 'ALP/'.trim($folder, '/').'/'.basename($filename);
        Storage::disk('s3')->put($key, $contents, ['ContentType' => $mime]);

        return $this->encode($key);
    }

    public function stream(string $fileId)
    {
        $key = $this->decode($fileId);
        abort_unless($key && str_starts_with($key, 'ALP/') && Storage::disk('s3')->exists($key), 404);

        $mime = Storage::disk('s3')->mimeType($key) ?: 'application/octet-stream';

        return response(Storage::disk('s3')->get($key), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($key).'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function delete(?string $fileId): void
    {
        $key = $fileId ? $this->decode($fileId) : null;
        if ($key && str_starts_with($key, 'ALP/')) {
            Storage::disk('s3')->delete($key);
        }
    }

    public function encode(string $key): string
    {
        return 's3.'.rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
    }

    public function decode(string $fileId): ?string
    {
        if (! str_starts_with($fileId, 's3.')) {
            return null;
        }
        $value = strtr(substr($fileId, 3), '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }
}
