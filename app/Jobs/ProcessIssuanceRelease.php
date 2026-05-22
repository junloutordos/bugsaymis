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
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ProcessIssuanceRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(public Issuance $issuance) {}

    public function handle(IssuanceService $svc): void
    {
        // 1. Generate PDF (stamp QR on scan, or render content PDF)
        try {
            $svc->generatePdf($this->issuance->fresh());
        } catch (\Throwable $e) {
            logger()->error('Issuance PDF generation failed in job', [
                'id'    => $this->issuance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // 2. Notify all recipients (email + bell + push)
        $recipients = $this->issuance->recipients()->with('user')->get();

        foreach ($recipients as $recipient) {
            $u = $recipient->user;
            if (! $u) continue;

            try {
                Mail::to($u->email)->send(new IssuanceReleasedMail($this->issuance, $u->name));
            } catch (\Throwable $e) {
                logger()->warning('Issuance email failed', ['user_id' => $u->id, 'error' => $e->getMessage()]);
            }

            try {
                NotificationService::notifyUser(
                    $u,
                    'Issuance',
                    $this->issuance->control_number,
                    "{$this->issuance->type_label}: {$this->issuance->title}",
                    route('issuances.show', $this->issuance->id),
                );
            } catch (\Throwable) {}
        }
    }
}
