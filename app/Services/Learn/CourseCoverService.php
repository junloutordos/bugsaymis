<?php

namespace App\Services\Learn;

use App\Models\Learn\Course;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Base64 → S3 upload and private-proxy serving for course cover photos.
 * Same encoding as WFH photos and Learn course files (CourseFileService):
 * Storage::disk('s3') only, never disk('public') — S3 Block Public Access
 * silently drops that ACL.
 *
 * PRESET_KEYS must stay in sync with resources/js/Constants/courseCoverPresets.js —
 * the preset's visual definition (gradient class) lives entirely in the frontend,
 * this list only guards against storing a garbage key.
 */
class CourseCoverService
{
    public const PRESET_KEYS = [
        'indigo-diagonal', 'sky-wave', 'navy-radial', 'slate-grid', 'indigo-sunrise', 'ocean-deep',
    ];

    private const ALLOWED_MIME = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function upload(Course $course, string $dataUri): void
    {
        if (str_contains($dataUri, ',')) {
            [$meta, $base64] = explode(',', $dataUri, 2);
        } else {
            $meta = '';
            $base64 = $dataUri;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            throw ValidationException::withMessages(['photo' => 'Invalid image data.']);
        }

        $mime = $this->mimeFromMeta($meta);
        $extension = $mime ? (self::ALLOWED_MIME[$mime] ?? null) : null;

        // Reject anything outside the image allowlist — an unrestricted MIME
        // (e.g. text/html, image/svg+xml) served inline on this app's origin
        // would be a stored-XSS vector.
        if ($extension === null) {
            throw ValidationException::withMessages(['photo' => 'Unsupported image type. Use PNG, JPEG, or WebP.']);
        }

        $this->deleteExisting($course);

        $s3Key = "Learn/{$course->id}/cover-" . uniqid() . ".{$extension}";
        Storage::disk('s3')->put($s3Key, $binary);

        $course->update(['cover_photo_s3_key' => $s3Key, 'cover_preset' => null]);
    }

    public function setPreset(Course $course, string $presetKey): void
    {
        if (! in_array($presetKey, self::PRESET_KEYS, true)) {
            throw ValidationException::withMessages(['preset' => 'Unknown cover preset.']);
        }

        $this->deleteExisting($course);

        $course->update(['cover_preset' => $presetKey, 'cover_photo_s3_key' => null]);
    }

    public function streamResponse(Course $course): Response
    {
        abort_if(! $course->cover_photo_s3_key, 404);
        abort_if(! Storage::disk('s3')->exists($course->cover_photo_s3_key), 404);

        return response(Storage::disk('s3')->get($course->cover_photo_s3_key), 200)
            ->header('Content-Type', $this->mimeFromExtension($course->cover_photo_s3_key))
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function deleteExisting(Course $course): void
    {
        if ($course->cover_photo_s3_key) {
            Storage::disk('s3')->delete($course->cover_photo_s3_key);
        }
    }

    private function mimeFromMeta(string $meta): ?string
    {
        if (preg_match('/^data:([a-zA-Z0-9\/\+\.\-]+);base64$/', $meta, $m)) {
            return $m[1];
        }

        return null;
    }

    private function mimeFromExtension(string $s3Key): string
    {
        return match (pathinfo($s3Key, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
