<?php

namespace App\Services;

use App\Models\ITJobRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\DigitalSignature;
use App\Services\DigitalSignatureService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ITJobRequestPdfService
{
    /**
     * Default recommendations by category.
     */
    private const DEFAULT_RECOMMENDATIONS = [
        'Hardware Repair'                => 'Repair/Replace defective hardware component(s)',
        'Hardware Installation'          => 'Install hardware component/peripheral',
        'Preventive Maintenance'         => 'Internal - Preventive Maintenance',
        'Software Installation'          => 'Install required software application',
        'Software Modification'          => 'Modify/Update existing software',
        'Software Development'           => 'Develop custom software solution',
        'Network Connection'             => 'Network troubleshooting and connection setup',
        'Account Access'                 => 'Create/Reset/Update user account credentials',
        'Graphic Design'                 => 'Create graphic design and layout',
        'Technical Assistance on Events' => 'Provide technical support for the event',
        'Video Editing/Production'       => 'Edit and produce video content',
        'Posting to Website'             => 'Upload/Update content on official website',
        'Posting to Social Media'        => 'Upload/Update content on official social media pages',
        'Poll Survey Creation'           => 'Create and set up online poll/survey',
        'DTR Generation'                 => 'Generate and issue DTR report',
        'DTR System Concerns'            => 'Investigate and resolve DTR system issue',
        'Online Meeting Request'         => 'Set up and facilitate online meeting',
        'CCTV Footage Review'            => 'Review CCTV footage for specified date/time',
        'CCTV Footage Retrieval'         => 'Retrieve and export requested CCTV footage',
        'SIMS Concerns'                  => 'Investigate and resolve SIMS concern',
        'Document Tracking Concerns'     => 'Investigate and resolve Document Tracking concern',
        'eNGAS Concerns'                 => 'Investigate and resolve eNGAS concern',
        'Other'                          => 'Provide appropriate technical assistance',
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Build the PDF bytes, store on S3/local, update pdf_path, return storage path.
     * Used by auto-generation when status reaches "Acted by MIS".
     */
    public function generate(ITJobRequest $jobRequest): string
    {
        $pdfBytes = $this->buildPdfBytes($jobRequest);

        $dir      = 'it_job_requests';
        $filename = $dir . '/' . $jobRequest->itjr_no . '.pdf';

        Storage::disk('public')->put($filename, $pdfBytes, [
            'ContentType' => 'application/pdf',
        ]);

        $jobRequest->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Stream a freshly-generated PDF directly from PHP memory.
     *
     * Does NOT read from S3 — generates inline on every request. Eliminates
     * all S3 read-back issues (driver->path(), get() returning false, mimeType
     * errors, ACL problems, etc.). PDF is small so regeneration is fast.
     */
    public function stream(ITJobRequest $jobRequest): StreamedResponse
    {
        $pdfBytes = $this->buildPdfBytes($jobRequest);
        $filename = 'ITJRF_' . $jobRequest->itjr_no . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($pdfBytes),
        ]);
    }

    /**
     * Generate a list-report PDF for a collection of IT Job Requests.
     * Streams the PDF bytes directly (no S3 storage).
     */
    public function exportList(
        \Illuminate\Support\Collection $records,
        User                           $preparedBy,
        ?User                          $notedBy,
        ?string                        $dateFrom,
        ?string                        $dateTo,
        ?string                        $category,
    ): StreamedResponse {
        $headerPath = public_path('images/report_header.jpeg');
        $footerPath = public_path('images/report_footer.jpeg');

        // Measure actual image heights so margins are exact.
        // Images are scaled to A4 width (210mm); height scales proportionally.
        $hInfo = @getimagesize($headerPath);
        $fInfo = @getimagesize($footerPath);
        $headerMm = $hInfo ? round(($hInfo[1] / $hInfo[0]) * 210) + 3 : 36;
        $footerMm = $fInfo ? round(($fInfo[1] / $fInfo[0]) * 210) + 3 : 36;

        $html = view('it-job-requests.export-pdf', compact(
            'records',
            'preparedBy',
            'notedBy',
            'dateFrom',
            'dateTo',
            'category',
        ))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => $headerMm,
            'margin_bottom' => $footerMm,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        // Set repeating header/footer — renders on every page at full paper width.
        $mpdf->SetHTMLHeader('<img src="' . $headerPath . '" style="width:100%; display:block;">');
        $mpdf->SetHTMLFooter('<img src="' . $footerPath . '" style="width:100%; display:block;">');

        $mpdf->SetTitle('IT Job Request Report');
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'ITJR_Report_' . now()->format('Y-m-d') . '.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length'      => strlen($pdfBytes),
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Build and return raw PDF bytes without storing anywhere.
     */
    private function buildPdfBytes(ITJobRequest $jobRequest): string
    {
        // Signature images are large base64 blobs; raise memory limit for this PDF build only
        $prevMemory = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        $jobRequest->loadMissing(['user.division', 'divisionChief', 'assignedTo']);

        $director = User::havingRole('OCD')->first();

        $recommendation = $jobRequest->recommendation
            ?: ($jobRequest->category
                ? (self::DEFAULT_RECOMMENDATIONS[$jobRequest->category] ?? $jobRequest->category)
                : '—');

        // Collect per-stage text badges first — sig images are only shown for stages that have been signed
        $sigBadges = [];
        $documentQr = null;
        $digitalSigs = DigitalSignature::where('signable_type', ITJobRequest::class)
            ->where('signable_id', $jobRequest->id)
            ->latest('signed_at')
            ->get();

        if ($digitalSigs->isNotEmpty()) {
            foreach ($digitalSigs as $ds) {
                $stage = $ds->metadata['stage'] ?? null;
                if ($stage && ! isset($sigBadges[$stage])) {
                    $sigBadges[$stage] = $ds->signed_at->format('M d, Y h:i A');
                }
            }

            // One document-level QR linking to the ITJR verification page
            $verifyUrl  = route('itjr.verify', $jobRequest->itjr_no);
            $documentQr = base64_encode(QrCode::format('svg')->size(120)->margin(1)->generate($verifyUrl));
        }

        // Signature images are gated on approval stage completion (business logic).
        // The ✓ Digitally Signed badge is separate — gated on DigitalSignature records above.
        $requesterSig  = $this->sigDataUri($jobRequest->user?->electronic_signature);
        $dcSig         = $jobRequest->dc_approval_date
                            ? $this->sigDataUri($jobRequest->divisionChief?->electronic_signature) : null;
        $directorSig   = $jobRequest->ocd_approval_date
                            ? $this->sigDataUri($director?->electronic_signature) : null;
        $assignedSig   = $jobRequest->action_taken
                            ? $this->sigDataUri($jobRequest->assignedTo?->electronic_signature) : null;
        $completionSig = $jobRequest->completed_at
                            ? $this->sigDataUri($jobRequest->user?->electronic_signature) : null;

        $html = view('it-job-requests.pdf', compact(
            'jobRequest',
            'director',
            'recommendation',
            'directorSig',
            'dcSig',
            'assignedSig',
            'requesterSig',
            'completionSig',
            'sigBadges',
            'documentQr'
        ))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('IT JRF — ' . $jobRequest->itjr_no);

        // Base64-embedded signature images can push the HTML past PHP's default
        // pcre.backtrack_limit (1 MB). Raise it temporarily so mPDF can parse.
        $prevBacktrack = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', (string) (10 * 1000 * 1000));
        $mpdf->WriteHTML($html);
        ini_set('pcre.backtrack_limit', (string) $prevBacktrack);

        $pdfBytes = $mpdf->Output('', 'S');
        ini_set('memory_limit', $prevMemory);

        return $pdfBytes;
    }

    /**
     * Convert a public-disk signature path (e.g. "signatures/file.png") to a
     * base64 data URI that mPDF can embed inline.
     *
     * Permanent S3 fix:
     * - Avoids Storage::disk()->mimeType() which makes a HEAD API call on S3
     *   and can throw even when 'throw' => false is set on the disk config.
     * - Derives MIME type from file extension instead (signatures are always
     *   PNG per upload validation; profile pics may be JPG/PNG/GIF/WebP).
     * - Entire method wrapped in try-catch so any S3/network error returns null
     *   (signature simply won't appear) rather than causing a 500.
     */
    private function sigDataUri(?string $storedPath): ?string
    {
        if (! $storedPath || $storedPath === '0') {
            return null;
        }

        $ext  = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };

        // Try S3 first (new uploads), then fall back to disk('public') for legacy uploads
        foreach (['s3', 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($storedPath)) {
                    $contents = Storage::disk($disk)->get($storedPath);
                    if ($contents) {
                        return 'data:' . $mime . ';base64,' . base64_encode($contents);
                    }
                }
            } catch (\Throwable $e) {
                logger()->warning("ITJobRequestPdfService: signature load failed on {$disk}", [
                    'path'  => $storedPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }
}
