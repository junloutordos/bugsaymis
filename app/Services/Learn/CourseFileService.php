<?php

namespace App\Services\Learn;

use App\Models\Learn\File as LearnFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Base64 → S3 upload and private-proxy serving for Learn files, following
 * the same encoding WFH photos use (Storage::disk('s3') only — never
 * disk('public'), since S3 Block Public Access silently drops that ACL).
 */
class CourseFileService
{
    public function upload(int $courseId, string $title, string $dataUri): LearnFile
    {
        if (str_contains($dataUri, ',')) {
            [$meta, $base64] = explode(',', $dataUri, 2);
        } else {
            $meta = '';
            $base64 = $dataUri;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            throw ValidationException::withMessages(['file' => 'Invalid file data.']);
        }

        $mime = $this->mimeFromMeta($meta) ?? 'application/octet-stream';
        $extension = $this->extensionFromMime($mime);
        $s3Key = "Learn/{$courseId}/" . uniqid() . ($extension ? ".{$extension}" : '');

        Storage::disk('s3')->put($s3Key, $binary);

        return LearnFile::create([
            'title' => $title,
            's3_key' => $s3Key,
            'mime_type' => $mime,
            'size_bytes' => strlen($binary),
        ]);
    }

    /** 's3.<base64url(s3Key)>' — same encoding WFH photos use. */
    public function encodeFileId(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public function decodeFileId(string $fileId): ?string
    {
        if (! str_starts_with($fileId, 's3.')) {
            return null;
        }

        $padded = strtr(substr($fileId, 3), '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($padded, true);

        return $decoded ?: null;
    }

    public function streamResponse(LearnFile $file): Response
    {
        if (! Storage::disk('s3')->exists($file->s3_key)) {
            abort(404);
        }

        return response(Storage::disk('s3')->get($file->s3_key), 200)
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . addslashes($file->title) . '"');
    }

    private function mimeFromMeta(string $meta): ?string
    {
        if (preg_match('/^data:([a-zA-Z0-9\/\+\.\-]+);base64$/', $meta, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extensionFromMime(string $mime): ?string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => null,
        };
    }
}
