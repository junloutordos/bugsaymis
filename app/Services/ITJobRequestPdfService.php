<?php

namespace App\Services;

use App\Models\ITJobRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $html = view('it-job-requests.export-pdf', compact(
            'records',
            'preparedBy',
            'notedBy',
            'dateFrom',
            'dateTo',
            'category',
            'headerPath',
            'footerPath',
        ))->render();

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 12,
            'margin_right'  => 12,
            'margin_top'    => 10,
            'margin_bottom' => 10,
            'tempDir'       => $tmpDir,
        ]);

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
        $jobRequest->loadMissing(['user.division', 'divisionChief', 'assignedTo']);

        $director = User::havingRole('OCD')->first();

        $recommendation = $jobRequest->recommendation
            ?: ($jobRequest->category
                ? (self::DEFAULT_RECOMMENDATIONS[$jobRequest->category] ?? $jobRequest->category)
                : '—');

        $directorSig  = $this->sigDataUri($director?->electronic_signature);
        $dcSig        = $this->sigDataUri($jobRequest->divisionChief?->electronic_signature);
        $assignedSig  = $this->sigDataUri($jobRequest->assignedTo?->electronic_signature);
        $requesterSig = $this->sigDataUri($jobRequest->user?->electronic_signature);

        $html = view('it-job-requests.pdf', compact(
            'jobRequest',
            'director',
            'recommendation',
            'directorSig',
            'dcSig',
            'assignedSig',
            'requesterSig'
        ))->render();

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => $tmpDir,
        ]);

        $mpdf->SetTitle('IT JRF — ' . $jobRequest->itjr_no);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Convert a public-disk signature path (e.g. "signatures/file.png") to a
     * base64 data URI that mPDF can embed inline.
     *
     * Uses Storage::disk('public') exclusively — compatible with both local
     * disk (dev) and S3 (production).
     */
    private function sigDataUri(?string $storedPath): ?string
    {
        if (! $storedPath) {
            return null;
        }

        if (! Storage::disk('public')->exists($storedPath)) {
            return null;
        }

        $contents = Storage::disk('public')->get($storedPath);
        $mime     = Storage::disk('public')->mimeType($storedPath) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
