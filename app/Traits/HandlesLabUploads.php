<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores base64 data-URI uploads to private S3 (Cloudflare WAF blocks
 * multipart/form-data). Returns the S3 key; serve via the /media proxy.
 */
trait HandlesLabUploads
{
    /**
     * @param  string|null  $dataUri  data:<mime>;base64,<payload>
     * @return string|null  S3 key, or null if no upload provided
     */
    protected function storeLabUpload(?string $dataUri, string $folder): ?string
    {
        if (! $dataUri || ! Str::startsWith($dataUri, 'data:')) {
            return null;
        }

        [$meta, $payload] = explode(',', $dataUri, 2);
        $binary = base64_decode($payload, true);
        if ($binary === false) {
            return null;
        }

        // ext from mime
        $mime = Str::between($meta, 'data:', ';');
        $ext = match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            default           => 'bin',
        };

        $key = trim($folder, '/') . '/' . Str::uuid() . '.' . $ext;
        Storage::disk('s3')->put($key, $binary);

        return $key;
    }
}
