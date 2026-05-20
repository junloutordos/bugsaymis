<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $requestType,   // e.g. 'IT Job Request'
        public readonly string $referenceNo,   // e.g. '2026-05-0012' or 'Leave #42'
        public readonly string $newStatus,     // e.g. 'Approved by Division Chief'
        public readonly string $url,           // route to the request
        public readonly ?string $remarks = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'request_type' => $this->requestType,
            'reference_no' => $this->referenceNo,
            'status'       => $this->newStatus,
            'url'          => $this->url,
            'remarks'      => $this->remarks,
        ];
    }

    public function toBroadcast(object $notifiable): array
    {
        return [
            'id'           => $this->id,
            'request_type' => $this->requestType,
            'reference_no' => $this->referenceNo,
            'status'       => $this->newStatus,
            'url'          => $this->url,
            'remarks'      => $this->remarks,
            'created_at'   => now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [];   // We broadcast manually via notifiable's channel
    }

    public function broadcastType(): string
    {
        return 'request.status.updated';
    }
}
