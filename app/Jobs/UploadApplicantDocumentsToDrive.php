<?php

namespace App\Jobs;

use App\Models\ApplicantDocument;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Moves a public job applicant's consolidated documents from the S3 staging
 * object to Google Drive. The upload used to run synchronously inside
 * POST /jobs/{vacancy}/apply, holding the request open 11-16s on a slow
 * Drive day and tripping the ALB p99 latency alarm.
 */
class UploadApplicantDocumentsToDrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var int[] */
    public array $backoff = [60, 300, 900, 1800];

    public function __construct(
        private readonly int $documentId,
        private readonly string $s3Key,
        private readonly string $driveFileName,
        private readonly string $mimeType,
    ) {}

    public function handle(GoogleDriveService $drive): void
    {
        $document = ApplicantDocument::find($this->documentId);

        // The applicant re-submitted before this job ran (updateOrCreate reuses
        // the row per applicant+type) — a newer job owns the row now, so this
        // upload is stale. Just drop our staging object.
        if (! $document || $document->file_path !== ApplicantDocument::pendingPath($this->s3Key)) {
            Storage::disk('s3')->delete($this->s3Key);

            return;
        }

        $raw = Storage::disk('s3')->get($this->s3Key);
        if ($raw === null) {
            Log::error("Applicant document staging object missing on S3: {$this->s3Key} (document #{$this->documentId})");

            return;
        }

        $result = $drive->uploadRaw($raw, $this->driveFileName, $this->mimeType);

        $document->update([
            'drive_file_id' => $result['file_id'],
            'drive_url'     => $result['link'],
            'file_path'     => $result['link'],
        ]);

        Storage::disk('s3')->delete($this->s3Key);
    }

    public function failed(\Throwable $e): void
    {
        // Keep the staging object so the upload can be replayed by hand.
        Log::error("Drive upload permanently failed for applicant document #{$this->documentId} (staging {$this->s3Key}): {$e->getMessage()}");
    }
}
