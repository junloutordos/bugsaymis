<?php

namespace App\Services\SPMS;

use App\Models\SPMS\IpcrTarget;
use App\Models\SPMS\MovChecklistItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MovChecklistService
{
    public function uploadEvidence(IpcrTarget $target, string $documentType, string $base64DataUri, int $submittedBy): MovChecklistItem
    {
        [$extension, $binary] = $this->decode($base64DataUri);

        $s3Key = sprintf(
            'spms/ipcr-mov/%d/%d/%s-%d.%s',
            $target->ipcr_id,
            $target->id,
            Str::slug($documentType),
            now()->timestamp,
            $extension
        );

        Storage::disk('s3')->put($s3Key, $binary);

        return MovChecklistItem::updateOrCreate(
            ['spms_ipcr_target_id' => $target->id, 'document_type' => $documentType],
            [
                'status' => 'submitted',
                's3_key' => $s3Key,
                'submitted_at' => now(),
                'submitted_by' => $submittedBy,
            ]
        );
    }

    public static function encodeFileId(string $s3Key): string
    {
        return 's3.'.rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public static function decodeFileId(string $fileId): string
    {
        $encoded = substr($fileId, 3); // strip 's3.' prefix
        $padLength = strlen($encoded) % 4 === 0 ? strlen($encoded) : strlen($encoded) + (4 - strlen($encoded) % 4);
        $padded = str_pad(strtr($encoded, '-_', '+/'), $padLength, '=');

        return base64_decode($padded);
    }

    private function decode(string $dataUri): array
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $dataUri, $matches)) {
            $extension = $matches[1];
            $binary = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1));
        } else {
            $extension = 'bin';
            $binary = base64_decode($dataUri);
        }

        return [$extension, $binary];
    }
}
