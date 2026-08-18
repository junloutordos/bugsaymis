<?php

namespace App\Jobs;

use App\Models\Issuance;
use App\Services\IssuanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ResendIssuanceEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Mirrors ProcessIssuanceRelease — stay below the queue connection
    // retry_after so the job is never re-released to another worker mid-flight.
    public int $timeout = 600;

    // Single attempt — re-running a batch resend on a flaky failure would
    // double-send emails to recipients who already succeeded.
    public int $tries = 1;

    public function __construct(public int $issuanceId, public array $recipientIds)
    {
        // Loops over every recipient synchronously — keep off 'default'
        // so it never blocks fast single-unit jobs.
        $this->onQueue('bulk');
    }

    public function handle(IssuanceService $svc): void
    {
        $issuance = Issuance::find($this->issuanceId);

        if (! $issuance) {
            logger()->error('ResendIssuanceEmails: issuance not found', [
                'issuance_id' => $this->issuanceId,
            ]);
            return;
        }

        $recipients = $issuance->recipients()->whereIn('id', $this->recipientIds)->with(['user', 'student'])->get();
        $sent       = 0;
        $skipped    = 0;
        $failed     = 0;

        foreach ($recipients as $recipient) {
            $status = $svc->deliverRecipientEmail($recipient, $issuance);
            match ($status) {
                'sent'    => $sent++,
                'skipped' => $skipped++,
                'failed'  => $failed++,
            };
        }

        logger()->info('ResendIssuanceEmails: complete', [
            'issuance_id' => $issuance->id,
            'requested'   => count($this->recipientIds),
            'sent'        => $sent,
            'skipped'     => $skipped,
            'failed'      => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('ResendIssuanceEmails: job FAILED', [
            'issuance_id'   => $this->issuanceId,
            'recipient_ids' => $this->recipientIds,
            'error'         => $e->getMessage(),
            'trace'         => $e->getTraceAsString(),
        ]);
    }
}
