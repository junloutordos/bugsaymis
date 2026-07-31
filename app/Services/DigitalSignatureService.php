<?php

namespace App\Services;

use App\Models\DigitalSignature;
use App\Models\User;
use Aws\Kms\KmsClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DigitalSignatureService
{
    /**
     * Verify the user's signature PIN.
     */
    public function verifyPin(User $user, string $pin): bool
    {
        if (empty($user->signature_pin)) {
            return false;
        }

        return Hash::check($pin, $user->signature_pin);
    }

    /**
     * Set or update the user's signature PIN.
     */
    public function setPin(User $user, string $pin): void
    {
        $user->signature_pin = $pin; // cast('hashed') handles bcrypt
        $user->save();
    }

    /**
     * Standard canvas dimensions (px) that every normalized signature is
     * resized/padded to fit within, so all signatures render at a consistent
     * visible size regardless of how they were drawn or uploaded.
     */
    private const NORMALIZED_WIDTH = 400;
    private const NORMALIZED_HEIGHT = 150;
    private const CROP_PADDING = 6;

    /**
     * Save a base64-encoded signature image to S3.
     * Returns the S3 key stored in users.electronic_signature.
     */
    public function saveSignatureImage(User $user, string $base64DataUri): string
    {
        // Strip the data URI prefix (data:image/png;base64,...)
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64DataUri);
        $imageData = base64_decode($base64);
        $imageData = $this->normalizeSignatureImage($imageData) ?? $imageData;

        $s3Key = "signatures/user_{$user->id}_" . time() . '.png';

        // Delete old signature — check both disks since old uploads used disk('public')
        $old = $user->electronic_signature;
        if ($old && $old !== '0') {
            if (Storage::disk('s3')->exists($old)) {
                Storage::disk('s3')->delete($old);
            } elseif (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        Storage::disk('s3')->put($s3Key, $imageData, ['ContentType' => 'image/png']);

        $user->electronic_signature = $s3Key;
        $user->save();

        return $s3Key;
    }

    /**
     * Get the user's signature image as a base64 data URI (for display).
     * Tries S3 first, then falls back to disk('public') for signatures
     * uploaded via the legacy admin upload flow.
     */
    public function getSignatureDataUri(User $user): ?string
    {
        return $this->getImageDataUriFromPath($user->electronic_signature ?? null);
    }

    /**
     * Resolve any stored signature path (user electronic_signature or
     * division signature_path) to a base64 data URI. Tries S3 first
     * (current uploads), then disk('public') for legacy uploads.
     */
    public function getImageDataUriFromPath(?string $path): ?string
    {
        if (empty($path) || $path === '0') {
            return null;
        }

        // Try S3 first (new uploads)
        try {
            if (Storage::disk('s3')->exists($path)) {
                $data = Storage::disk('s3')->get($path);
                return 'data:image/png;base64,' . base64_encode($data);
            }
        } catch (\Exception) {}

        // Fall back to disk('public') for legacy uploads
        try {
            if (Storage::disk('public')->exists($path)) {
                $data = Storage::disk('public')->get($path);
                return 'data:image/png;base64,' . base64_encode($data);
            }
        } catch (\Exception) {}

        return null;
    }

    /**
     * Cryptographically sign a document and create a DigitalSignature record.
     * Uses AWS KMS when configured, falls back to HMAC-SHA256 for local dev.
     */
    public function sign(
        User $signer,
        string $signableType,
        int $signableId,
        string $documentTitle,
        string $contentToHash,
        array $metadata = []
    ): DigitalSignature {
        $token        = (string) Str::uuid();
        $documentHash = hash('sha256', $contentToHash);
        $message      = $documentHash . '|' . $token . '|' . $signer->id;

        [$signature, $signatureType] = $this->kmsAvailable()
            ? $this->kmsSign($message)
            : [$this->hmacSign($message), 'hmac'];

        return DigitalSignature::create([
            'signable_type'      => $signableType,
            'signable_id'        => $signableId,
            'signer_id'          => $signer->id,
            'document_hash'      => $documentHash,
            'signature'          => $signature,
            'signature_type'     => $signatureType,
            'verification_token' => $token,
            'document_title'     => $documentTitle,
            'metadata'           => $metadata,
            'signed_at'          => now(),
        ]);
    }

    /**
     * Verify a signed document by token. Returns the record if valid, null if tampered or not found.
     * Routes to KMS or HMAC verification based on the record's signature_type.
     */
    public function verify(string $token): ?DigitalSignature
    {
        $record = DigitalSignature::with('signer')
            ->where('verification_token', $token)
            ->first();

        if (! $record) {
            return null;
        }

        $message = $record->document_hash . '|' . $token . '|' . $record->signer_id;
        $valid   = $record->signature_type === 'kms'
            ? $this->kmsVerify($message, $record->signature)
            : hash_equals($this->hmacSign($message), $record->signature);

        return $valid ? $record : null;
    }

    // ── Signature image normalization ─────────────────────────────────────────

    /**
     * Auto-crop a signature image to the bounding box of its ink (non-transparent /
     * non-white pixels), then resize+pad it onto a standard transparent canvas so
     * every stored signature — however it was drawn or uploaded — renders at a
     * consistent, legible size across all print/PDF templates.
     *
     * Returns re-encoded PNG binary data, or null if the image could not be
     * processed (caller should fall back to the original data).
     */
    public function normalizeSignatureImage(string $imageData): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $src = @imagecreatefromstring($imageData);
        if ($src === false) {
            return null;
        }

        imagesavealpha($src, true);
        $srcWidth  = imagesx($src);
        $srcHeight = imagesy($src);

        $bbox = $this->inkBoundingBox($src, $srcWidth, $srcHeight);
        if ($bbox === null) {
            // Blank/empty image — nothing to normalize, keep original.
            imagedestroy($src);
            return null;
        }

        [$minX, $minY, $maxX, $maxY] = $bbox;

        // Pad the crop box a little so strokes aren't clipped at the edge.
        $minX = max(0, $minX - self::CROP_PADDING);
        $minY = max(0, $minY - self::CROP_PADDING);
        $maxX = min($srcWidth - 1, $maxX + self::CROP_PADDING);
        $maxY = min($srcHeight - 1, $maxY + self::CROP_PADDING);

        $cropWidth  = $maxX - $minX + 1;
        $cropHeight = $maxY - $minY + 1;

        $cropped = imagecreatetruecolor($cropWidth, $cropHeight);
        imagesavealpha($cropped, true);
        imagealphablending($cropped, false);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefill($cropped, 0, 0, $transparent);
        imagealphablending($cropped, true);

        imagecopy($cropped, $src, 0, 0, $minX, $minY, $cropWidth, $cropHeight);
        imagedestroy($src);

        // Scale proportionally to fit within the standard canvas, then center it.
        $scale = min(
            self::NORMALIZED_WIDTH / $cropWidth,
            self::NORMALIZED_HEIGHT / $cropHeight
        );
        // Don't upscale tiny signatures excessively — cap scale factor to avoid
        // pixelating a very small crop into a blurry oversized image.
        $scale = min($scale, 4.0);

        $targetWidth  = max(1, (int) round($cropWidth * $scale));
        $targetHeight = max(1, (int) round($cropHeight * $scale));

        $canvas = imagecreatetruecolor(self::NORMALIZED_WIDTH, self::NORMALIZED_HEIGHT);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        $canvasTransparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $canvasTransparent);
        imagealphablending($canvas, true);

        $offsetX = (int) round((self::NORMALIZED_WIDTH - $targetWidth) / 2);
        $offsetY = (int) round((self::NORMALIZED_HEIGHT - $targetHeight) / 2);

        imagecopyresampled(
            $canvas, $cropped,
            $offsetX, $offsetY, 0, 0,
            $targetWidth, $targetHeight, $cropWidth, $cropHeight
        );
        imagedestroy($cropped);

        ob_start();
        imagepng($canvas);
        $output = ob_get_clean();
        imagedestroy($canvas);

        return $output ?: null;
    }

    /**
     * Compute the bounding box of "ink" pixels in the image — pixels that are
     * neither fully transparent nor near-white (to handle signatures drawn on
     * an opaque white background instead of a transparent one).
     *
     * Returns [minX, minY, maxX, maxY] or null if no ink pixels were found.
     */
    private function inkBoundingBox($image, int $width, int $height): ?array
    {
        // Sample on a grid for performance on large uploaded images, then
        // refine — for typical signature sizes (a few hundred px) this is
        // effectively a full scan anyway.
        $step = max(1, (int) floor(min($width, $height) / 300));

        $minX = null; $minY = null; $maxX = null; $maxY = null;
        $whiteThreshold = 245; // RGB values above this (all channels) treated as blank paper

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F; // 0 = opaque, 127 = fully transparent
                if ($alpha >= 120) {
                    continue; // fully/near transparent — not ink
                }

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r >= $whiteThreshold && $g >= $whiteThreshold && $b >= $whiteThreshold) {
                    continue; // near-white background — not ink
                }

                if ($minX === null || $x < $minX) $minX = $x;
                if ($minY === null || $y < $minY) $minY = $y;
                if ($maxX === null || $x > $maxX) $maxX = $x;
                if ($maxY === null || $y > $maxY) $maxY = $y;
            }
        }

        if ($minX === null) {
            return null;
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    // ── Internal signing helpers ──────────────────────────────────────────────

    private function kmsAvailable(): bool
    {
        return ! empty(config('services.kms.signing_key_arn'));
    }

    private function kmsClient(): KmsClient
    {
        return new KmsClient([
            'region'  => config('services.kms.region', 'ap-southeast-1'),
            'version' => 'latest',
        ]);
    }

    private function kmsSign(string $message): array
    {
        $result = $this->kmsClient()->sign([
            'KeyId'            => config('services.kms.signing_key_arn'),
            'Message'          => $message,
            'MessageType'      => 'RAW',
            'SigningAlgorithm' => 'RSASSA_PKCS1_V1_5_SHA_256',
        ]);

        return [base64_encode((string) $result['Signature']), 'kms'];
    }

    private function kmsVerify(string $message, string $signature): bool
    {
        try {
            $result = $this->kmsClient()->verify([
                'KeyId'            => config('services.kms.signing_key_arn'),
                'Message'          => $message,
                'MessageType'      => 'RAW',
                'Signature'        => base64_decode($signature),
                'SigningAlgorithm' => 'RSASSA_PKCS1_V1_5_SHA_256',
            ]);

            return (bool) $result['SignatureValid'];
        } catch (\Exception) {
            return false;
        }
    }

    private function hmacSign(string $message): string
    {
        return hash_hmac('sha256', $message, config('app.key'));
    }

    /**
     * Generate a QR code PNG (binary) linking to the verification page.
     */
    public function generateQrSvg(string $token, int $size = 150): string
    {
        $url = route('document.verify', $token);

        return QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->generate($url);
    }

    /**
     * Embed a QR code + signature block into an mPDF instance.
     * Call this just before $mpdf->Output().
     */
    public function embedInPdf(\Mpdf\Mpdf $mpdf, DigitalSignature $sig, ?string $signatureDataUri = null): void
    {
        $qrSvg    = $this->generateQrSvg($sig->verification_token);
        $qrBase64 = base64_encode($qrSvg);
        $signedAt = $sig->signed_at->format('F d, Y h:i A');

        $signerName     = $sig->signer->name ?? '';
        $signerPosition = $sig->signer->position ?? '';

        $signatureImgHtml = $signatureDataUri
            ? "<img src=\"{$signatureDataUri}\" style=\"max-height:40px;max-width:160px;\">"
            : "<span style=\"font-style:italic;color:#64748b;\">No signature image</span>";

        $html = "
        <div style=\"border:1px solid #e2e8f0;border-radius:6px;padding:10px 14px;margin-top:16px;display:flex;align-items:center;gap:16px;font-family:Arial,sans-serif;font-size:9pt;\">
            <div>
                <img src=\"data:image/svg+xml;base64,{$qrBase64}\" style=\"width:80px;height:80px;\">
            </div>
            <div>
                <div style=\"font-weight:bold;color:#1e293b;margin-bottom:2px;\">Digitally Signed</div>
                <div style=\"color:#475569;\">{$signatureImgHtml}</div>
                <div style=\"color:#1e293b;font-weight:600;\">{$signerName}</div>
                <div style=\"color:#64748b;\">{$signerPosition}</div>
                <div style=\"color:#94a3b8;font-size:8pt;\">Signed: {$signedAt}</div>
                <div style=\"color:#94a3b8;font-size:7.5pt;\">Scan QR to verify authenticity</div>
            </div>
        </div>";

        $mpdf->WriteHTML($html);
    }
}
