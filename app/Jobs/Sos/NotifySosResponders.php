<?php

namespace App\Jobs\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySosResponders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $sosAlertId,
        public readonly int $tierId,
    ) {}

    public function handle(SosNotificationDispatchService $dispatch): void
    {
        $alert = SosAlert::find($this->sosAlertId);
        $tier  = SosEscalationTier::find($this->tierId);

        if (! $alert || ! $tier) return;

        $dispatch->notifyTier($alert, $tier);
    }
}
