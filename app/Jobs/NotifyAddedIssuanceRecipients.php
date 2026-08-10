<?php

namespace App\Jobs;

use App\Mail\IssuanceReleasedMail;
use App\Models\Issuance;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class NotifyAddedIssuanceRecipients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Mirrors ProcessIssuanceRelease/ResendIssuanceEmails — stay below the
    // queue connection retry_after so the job is never re-released to
    // another worker mid-flight.
    public int $timeout = 600;

    // Single attempt — re-running on a flaky failure would double-notify
    // recipients who already succeeded.
    public int $tries = 1;

    public function __construct(public int $issuanceId, public array $recipientIds) {}

    public function handle(): void
    {
        $issuance = Issuance::find($this->issuanceId);

        if (! $issuance) {
            logger()->error('NotifyAddedIssuanceRecipients: issuance not found', [
                'issuance_id' => $this->issuanceId,
            ]);
            return;
        }

        $recipients = $issuance->recipients()->whereIn('id', $this->recipientIds)->with('user')->get();
        $sent    = 0;
        $skipped = 0;
        $failed  = 0;

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
                logger()->warning('NotifyAddedIssuanceRecipients: email failed', [
                    'issuance_id'  => $issuance->id,
                    'recipient_id' => $recipient->id,
                    'user_id'      => $u->id,
                    'email'        => $u->email,
                    'error'        => $e->getMessage(),
                ]);
            }

            try {
                NotificationService::notifyUser(
                    $u,
                    'Issuance',
                    $issuance->display_number,
                    ($issuance->isSupplement() ? $issuance->document_kind_label : $issuance->type_label) . ": {$issuance->title}",
                    route('issuances.show', $issuance->id),
                );
            } catch (\Throwable $e) {
                logger()->warning('NotifyAddedIssuanceRecipients: bell/push failed', [
                    'issuance_id'  => $issuance->id,
                    'recipient_id' => $recipient->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        logger()->info('NotifyAddedIssuanceRecipients: complete', [
            'issuance_id' => $issuance->id,
            'requested'   => count($this->recipientIds),
            'sent'        => $sent,
            'skipped'     => $skipped,
            'failed'      => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyAddedIssuanceRecipients: job FAILED', [
            'issuance_id'   => $this->issuanceId,
            'recipient_ids' => $this->recipientIds,
            'error'         => $e->getMessage(),
            'trace'         => $e->getTraceAsString(),
        ]);
    }
}
