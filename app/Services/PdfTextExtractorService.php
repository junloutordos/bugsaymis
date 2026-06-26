<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PdfTextExtractorService
{
    // Minimum character count to consider smalot extraction "successful" and skip Textract
    private const MIN_TEXT_LENGTH = 50;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Extract text from an S3-stored PDF.
     * Tries smalot/pdfparser first; falls back to AWS Textract for image-only PDFs.
     */
    public function extractFromS3(string $s3Path): string
    {
        $bytes = Storage::disk('s3')->get($s3Path);

        if (! $bytes) {
            logger()->warning('PdfTextExtractorService: could not retrieve S3 file', ['path' => $s3Path]);
            return '';
        }

        $text = $this->tryPdfParser($bytes);

        if (strlen(trim($text)) >= self::MIN_TEXT_LENGTH) {
            return $this->cleanText($text);
        }

        // Image-based or empty PDF — try Textract
        logger()->info('PdfTextExtractorService: smalot yielded short text, trying Textract', [
            'path'   => $s3Path,
            'length' => strlen(trim($text)),
        ]);

        try {
            $text = $this->extractViaTextract($s3Path);
        } catch (\Throwable $e) {
            logger()->warning('PdfTextExtractorService: Textract failed', [
                'path'  => $s3Path,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->cleanText($text);
    }

    /**
     * Extract plain text from an HTML string (for editor-mode issuances).
     */
    public function extractFromHtml(string $html): string
    {
        return $this->cleanText(strip_tags($html));
    }

    /**
     * Test that Textract is reachable and not blocked by an org SCP.
     * Returns true if accessible, false if blocked or unavailable.
     */
    public function testTextractConnection(): bool
    {
        try {
            $textract = $this->makeTextractClient();
            // Send a deliberately invalid JobId — expect InvalidJobIdException, not AccessDeniedException
            $textract->getDocumentTextDetection(['JobId' => 'connection-test-invalid-id']);
        } catch (\Aws\Textract\Exception\TextractException $e) {
            $code = $e->getAwsErrorCode() ?? '';
            if (str_contains($code, 'AccessDenied') || str_contains($code, 'UnauthorizedOperation')) {
                logger()->warning('PdfTextExtractorService: Textract blocked by SCP', ['code' => $code]);
                return false;
            }
            // Any other Textract exception (like InvalidJobIdException) means the service is reachable
            return true;
        } catch (\Throwable $e) {
            logger()->warning('PdfTextExtractorService: Textract connection error', ['error' => $e->getMessage()]);
            return false;
        }

        return false;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function tryPdfParser(string $bytes): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseContent($bytes);
            return $pdf->getText();
        } catch (\Throwable $e) {
            logger()->debug('PdfTextExtractorService: smalot parse failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function extractViaTextract(string $s3Path): string
    {
        $textract = $this->makeTextractClient();
        $bucket   = config('filesystems.disks.s3.bucket');

        $result = $textract->startDocumentTextDetection([
            'DocumentLocation' => [
                'S3Object' => ['Bucket' => $bucket, 'Name' => $s3Path],
            ],
        ]);

        $jobId    = $result['JobId'];
        $attempts = 0;
        $status   = null;

        do {
            sleep(5);
            $status = $textract->getDocumentTextDetection(['JobId' => $jobId]);
            $attempts++;
        } while ($status['JobStatus'] === 'IN_PROGRESS' && $attempts < 20);

        if ($status['JobStatus'] !== 'SUCCEEDED') {
            logger()->warning('PdfTextExtractorService: Textract job did not succeed', [
                'job_id' => $jobId,
                'status' => $status['JobStatus'],
            ]);
            return '';
        }

        $lines = $this->collectTextLines($textract, $jobId, $status);

        logger()->info('PdfTextExtractorService: Textract done', [
            'job_id' => $jobId,
            'lines'  => count($lines),
        ]);

        return implode("\n", $lines);
    }

    private function collectTextLines(\Aws\Textract\TextractClient $textract, string $jobId, array $firstPage): array
    {
        $lines = [];

        foreach ($firstPage['Blocks'] ?? [] as $block) {
            if ($block['BlockType'] === 'LINE') {
                $lines[] = $block['Text'];
            }
        }

        $nextToken = $firstPage['NextToken'] ?? null;
        while ($nextToken) {
            $page = $textract->getDocumentTextDetection(['JobId' => $jobId, 'NextToken' => $nextToken]);
            foreach ($page['Blocks'] ?? [] as $block) {
                if ($block['BlockType'] === 'LINE') {
                    $lines[] = $block['Text'];
                }
            }
            $nextToken = $page['NextToken'] ?? null;
        }

        return $lines;
    }

    private function makeTextractClient(): \Aws\Textract\TextractClient
    {
        $config = [
            'region'  => config('filesystems.disks.s3.region', 'ap-southeast-1'),
            'version' => 'latest',
        ];

        // Only pass explicit credentials when configured; otherwise rely on ECS task role
        $key = config('filesystems.disks.s3.key');
        if ($key) {
            $config['credentials'] = [
                'key'    => $key,
                'secret' => config('filesystems.disks.s3.secret'),
            ];
        }

        return new \Aws\Textract\TextractClient($config);
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
