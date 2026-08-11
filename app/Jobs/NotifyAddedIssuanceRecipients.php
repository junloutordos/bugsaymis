<?php

namespace App\Jobs;

use App\Models\Issuance;
use App\Services\IssuanceService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

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

        $svc = app(IssuanceService::class);

        $recipients = $issuance->recipients()->whereIn('id', $this->recipientIds)->with(['user', 'student'])->get();
        $sent    = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($recipients as $recipient) {
            $status = $svc->deliverRecipientEmail($recipient, $issuance);
            match ($status) {
                'sent'    => $sent++,
                'skipped' => $skipped++,
                'failed'  => $failed++,
            };

            if ($status === 'sent' && ! $recipient->student_id) {
                try {
                    NotificationService::notifyUser(
                        $recipient->user,
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
