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
