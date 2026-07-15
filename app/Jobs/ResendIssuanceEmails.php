<?php

namespace App\Jobs;

use App\Mail\IssuanceReleasedMail;
use App\Models\Issuance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class ResendIssuanceEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Mirrors ProcessIssuanceRelease — stay below the queue connection
    // retry_after so the job is never re-released to another worker mid-flight.
    public int $timeout = 600;

    // Single attempt — re-running a batch resend on a flaky failure would
    // double-send emails to recipients who already succeeded.
    public int $tries = 1;

    public function __construct(public int $issuanceId, public array $recipientIds) {}

    public function handle(): void
    {
        $issuance = Issuance::find($this->issuanceId);

        if (! $issuance) {
            logger()->error('ResendIssuanceEmails: issuance not found', [
                'issuance_id' => $this->issuanceId,
            ]);
            return;
        }

        $recipients = $issuance->recipients()->whereIn('id', $this->recipientIds)->with('user')->get();
        $sent       = 0;
        $skipped    = 0;
        $failed     = 0;

        foreach ($recipients as $recipient) {
            $u = $recipient->user;
            if (! $u || empty($u->email)) {
                $skipped++;
                $recipient->update([
                    'email_status' => 'skipped',
                    'email_error'  => 'No email on file for this recipient.',
                ]);
                continue;
            }

            try {
                Mail::to($u->email)->send(new IssuanceReleasedMail($issuance, $u->name));
                $sent++;
                $recipient->update(['email_status' => 'sent', 'emailed_at' => now(), 'email_error' => null]);
            } catch (\Throwable $e) {
                $failed++;
                $recipient->update(['email_status' => 'failed', 'email_error' => $e->getMessage()]);
                logger()->warning('ResendIssuanceEmails: email failed', [
                    'issuance_id'  => $issuance->id,
                    'recipient_id' => $recipient->id,
                    'user_id'      => $u->id,
                    'email'        => $u->email,
                    'error'        => $e->getMessage(),
                ]);
            }
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
