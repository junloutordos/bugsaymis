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

        // Approved by — active user with "Director" in position
        $director = User::where('position', 'like', '%Director%')
            ->where('status', 'active')
            ->first();

        // Resolve recommendation: stored value wins over category default
        $recommendation = $jobRequest->recommendation
            ?: ($jobRequest->category
                ? (self::DEFAULT_RECOMMENDATIONS[$jobRequest->category] ?? $jobRequest->category)
                : '—');

        // Convert signatures to base64 data URIs so mPDF can embed them
        $directorSig   = $this->sigDataUri($director?->electronic_signature);
        $dcSig         = $this->sigDataUri($jobRequest->divisionChief?->electronic_signature);
        $assignedSig   = $this->sigDataUri($jobRequest->assignedTo?->electronic_signature);

        $html = view('it-job-requests.pdf', compact(
            'jobRequest',
            'director',
            'recommendation',
            'directorSig',
            'dcSig',
            'assignedSig'
        ))->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_left'   => 15,
            'margin_right'  => 15,
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'tempDir'       => storage_path('app/tmp'),
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

    private function sigDataUri(?string $filename): ?string
    {
        if (! $filename) return null;

        $path = storage_path('app/public/signatures/' . $filename);
        if (! file_exists($path)) return null;

        $mime = mime_content_type($path) ?: 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}
