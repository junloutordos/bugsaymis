<?php

namespace App\Jobs;

use App\Models\Issuance;
use App\Models\OedIssuance;
use App\Services\PdfTextExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Up to 100s for Textract polling (20 attempts × 5s) plus buffer
    public int $timeout = 180;
    public int $tries   = 2;

    /**
     * @param string $modelType 'issuance' | 'oed_issuance'
     * @param int    $modelId
     */
    public function __construct(
        public string $modelType,
        public int    $modelId,
    ) {}

    public function handle(PdfTextExtractorService $extractor): void
    {
        [$model, $s3Path, $htmlContent] = $this->resolveModel();

        if (! $model) {
            logger()->warning('ExtractDocumentTextJob: model not found', [
                'type' => $this->modelType,
                'id'   => $this->modelId,
            ]);
            return;
        }

        try {
            $text = $htmlContent !== null
                ? $extractor->extractFromHtml($htmlContent)
                : $extractor->extractFromS3($s3Path);

            $model->update(['content_text' => $text ?: null]);

            logger()->info('ExtractDocumentTextJob: done', [
                'type'   => $this->modelType,
                'id'     => $this->modelId,
                'length' => strlen($text),
            ]);
        } catch (\Throwable $e) {
            logger()->error('ExtractDocumentTextJob: failed', [
                'type'  => $this->modelType,
                'id'    => $this->modelId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('ExtractDocumentTextJob: job FAILED', [
            'type'  => $this->modelType,
            'id'    => $this->modelId,
            'error' => $e->getMessage(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveModel(): array
    {
        if ($this->modelType === 'issuance') {
            $model = Issuance::find($this->modelId);
            if (! $model) {
                return [null, null, null];
            }

            if ($model->content) {
                // Editor-mode: content is HTML in the DB, no S3 download needed
                return [$model, null, $model->content];
            }

            // Scan/upload mode: final PDF is stored at this S3 key after release
            $s3Path = 'issuances/' . $model->control_number . '.pdf';
            return [$model, $s3Path, null];
        }

        if ($this->modelType === 'oed_issuance') {
            $model = OedIssuance::find($this->modelId);
            if (! $model) {
                return [null, null, null];
            }
            return [$model, $model->file_path, null];
        }

        return [null, null, null];
    }
}
