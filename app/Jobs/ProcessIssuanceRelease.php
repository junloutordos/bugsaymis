<?php

namespace App\Jobs;

use App\Mail\IssuanceReleasedMail;
use App\Models\Issuance;
use App\Services\IssuanceService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class ProcessIssuanceRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Job upper-bound. MUST stay below the queue connection retry_after
    // (see config/queue.php → REDIS_QUEUE_RETRY_AFTER, set to 900s) so the
    // job is never re-released to another worker mid-flight.
    public int $timeout = 600;

    // Single attempt — re-running PDF stamp + 300+ emails on a flaky
    // failure would double-send notifications. Surface the failure instead.
    public int $tries = 1;

    // Pass the primitive ID, not the Eloquent model. This avoids the
    // SerializesModels deserialization path entirely (which previously
    // produced "__PHP_Incomplete_Class" errors during rolling deploys
    // where the old worker received a payload referencing a class it
    // had not autoloaded yet). We re-fetch fresh on handle().
    public function __construct(public int $issuanceId) {}

    public function handle(IssuanceService $svc): void
    {
        $issuance = Issuance::find($this->issuanceId);

        if (! $issuance) {
            logger()->error('ProcessIssuanceRelease: issuance not found', [
                'issuance_id' => $this->issuanceId,
            ]);
            return;
        }

        logger()->info('ProcessIssuanceRelease: start', [
            'issuance_id'     => $issuance->id,
            'control_number'  => $issuance->control_number,
            'has_attachment'  => (bool) $issuance->attachment_path,
        ]);

        // 1. Generate PDF (stamp QR on scan, or render content PDF)
        try {
            $path = $svc->generatePdf($issuance);
            logger()->info('ProcessIssuanceRelease: PDF generated', [
                'issuance_id' => $issuance->id,
                'path'        => $path,
            ]);
        } catch (\Throwable $e) {
            logger()->error('ProcessIssuanceRelease: PDF generation failed', [
                'issuance_id' => $issuance->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
        }

        // 2. Notify all recipients (email + bell + push)
        $recipients = $issuance->recipients()->with('user')->get();
        $sent       = 0;
        $skipped    = 0;
        $failed     = 0;

        foreach ($recipients as $recipient) {
            $u = $recipient->user;
            if (! $u || empty($u->email)) {
                $skipped++;
                continue;
            }

            try {
                Mail::to($u->email)->send(new IssuanceReleasedMail($issuance, $u->name));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning('ProcessIssuanceRelease: email failed', [
                    'issuance_id' => $issuance->id,
                    'user_id'     => $u->id,
                    'email'       => $u->email,
                    'error'       => $e->getMessage(),
                ]);
            }

            try {
                NotificationService::notifyUser(
                    $u,
                    'Issuance',
                    $issuance->control_number,
                    "{$issuance->type_label}: {$issuance->title}",
                    route('issuances.show', $issuance->id),
                );
            } catch (\Throwable $e) {
                logger()->warning('ProcessIssuanceRelease: bell/push failed', [
                    'issuance_id' => $issuance->id,
                    'user_id'     => $u->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        logger()->info('ProcessIssuanceRelease: complete', [
            'issuance_id' => $issuance->id,
            'recipients'  => $recipients->count(),
            'sent'        => $sent,
            'skipped'     => $skipped,
            'failed'      => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('ProcessIssuanceRelease: job FAILED', [
            'issuance_id' => $this->issuanceId,
            'error'       => $e->getMessage(),
            'trace'       => $e->getTraceAsString(),
        ]);
    }
}
