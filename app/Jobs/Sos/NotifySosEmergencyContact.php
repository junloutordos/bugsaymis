<?php

namespace App\Jobs\Sos;

use App\Models\Sos\SosAlert;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySosEmergencyContact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $sosAlertId) {}

    public function handle(SosNotificationDispatchService $dispatch): void
    {
        $alert = SosAlert::find($this->sosAlertId);
        if (! $alert) return;

        $dispatch->notifyEmergencyContact($alert);
    }
}
