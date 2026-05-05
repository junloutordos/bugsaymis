<?php

namespace App\Services;

use App\Models\ITJobRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

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

    /**
     * Generate the PDF for a job request, store it, update pdf_path, and return the storage path.
     */
    public function generate(ITJobRequest $jobRequest): string
    {
        $jobRequest->loadMissing(['user.division', 'divisionChief', 'assignedTo']);

        // Approved by — use OCD role (same source as WFH accomplishments signatory)
        $director = User::havingRole('OCD')->first();

        // Resolve recommendation: stored value wins over category default
        $recommendation = $jobRequest->recommendation
            ?: ($jobRequest->category
                ? (self::DEFAULT_RECOMMENDATIONS[$jobRequest->category] ?? $jobRequest->category)
                : '—');

        // Convert signatures to base64 data URIs so mPDF can embed them.
        // electronic_signature is stored as a public-disk relative path (e.g. "signatures/abc.png").
        $directorSig = $this->sigDataUri($director?->electronic_signature);
        $dcSig       = $this->sigDataUri($jobRequest->divisionChief?->electronic_signature);
        $assignedSig = $this->sigDataUri($jobRequest->assignedTo?->electronic_signature);

        $html = view('it-job-requests.pdf', compact(
            'jobRequest',
            'director',
            'recommendation',
            'directorSig',
            'dcSig',
            'assignedSig'
        ))->render();

        // Ensure mPDF temp dir exists (may not exist on fresh ECS container boot)
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

        $dir      = 'it_job_requests';
        $filename = $dir . '/' . $jobRequest->itjr_no . '.pdf';

        Storage::disk('public')->put($filename, $mpdf->Output('', 'S'));

        $jobRequest->update(['pdf_path' => $filename]);

        return $filename;
    }

    /**
     * Stream the stored PDF; regenerate if missing.
     */
    public function stream(ITJobRequest $jobRequest): \Illuminate\Http\Response
    {
        if (! $jobRequest->pdf_path || ! Storage::disk('public')->exists($jobRequest->pdf_path)) {
            $this->generate($jobRequest);
            $jobRequest->refresh();
        }

        $content  = Storage::disk('public')->get($jobRequest->pdf_path);
        $filename = 'ITJRF_' . $jobRequest->itjr_no . '.pdf';

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert a public-disk signature path (e.g. "signatures/file.png") to a
     * base64 data URI that mPDF can embed inline.
     *
     * Uses Storage::disk('public') exclusively — compatible with both the local
     * disk (dev) and S3 (production). Avoids ->path() and file_get_contents()
     * which only work on local disks.
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
