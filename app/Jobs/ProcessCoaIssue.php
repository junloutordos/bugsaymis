<?php

namespace App\Jobs;

use App\Mail\CoaIssuedMail;
use App\Models\Administration\CoaVisit;
use App\Services\CertificateOfAppearanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class ProcessCoaIssue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    // Single attempt — re-running would double-send certificate emails to
    // external visitors. Failures are surfaced per certificate via email_status.
    public int $tries = 1;

    // Primitive ID, not the model — avoids SerializesModels deserialization
    // issues during rolling deploys (see ProcessIssuanceRelease).
    public function __construct(public int $visitId)
    {
        // Loops over every certificate in the visit (PDF + email each) —
        // keep off 'default' so it never blocks fast single-unit jobs.
        $this->onQueue('bulk');
    }

    public function handle(CertificateOfAppearanceService $svc): void
    {
        $visit = CoaVisit::with('certificates')->find($this->visitId);

        if (! $visit) {
            logger()->error('ProcessCoaIssue: visit not found', ['visit_id' => $this->visitId]);
            return;
        }

        // 1. Generate every PDF first so attachments exist by mail time
        foreach ($visit->certificates as $cert) {
            try {
                $svc->generatePdf($cert);
            } catch (\Throwable $e) {
                logger()->error('ProcessCoaIssue: PDF generation failed', [
                    'certificate_id' => $cert->id,
                    'control_number' => $cert->control_number,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        // 2. Email each visitor their own certificate
        $sent = 0;
        $failed = 0;

        foreach ($visit->certificates as $cert) {
            try {
                Mail::to($cert->email)->send(new CoaIssuedMail($cert));
                $cert->update(['email_status' => 'sent', 'emailed_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $cert->update(['email_status' => 'failed']);
                $failed++;
                logger()->warning('ProcessCoaIssue: email failed', [
                    'certificate_id' => $cert->id,
                    'email'          => $cert->email,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        logger()->info('ProcessCoaIssue: complete', [
            'visit_id'     => $visit->id,
            'certificates' => $visit->certificates->count(),
            'sent'         => $sent,
            'failed'       => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('ProcessCoaIssue: job FAILED', [
            'visit_id' => $this->visitId,
            'error'    => $e->getMessage(),
        ]);
    }
}
