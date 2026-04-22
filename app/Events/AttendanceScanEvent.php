<?php

namespace App\Events;

use App\Models\StudentAttendance\StudentAttendanceLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired immediately after a student attendance log is created.
 * Broadcasts on the public 'attendance' channel so the gate monitor
 * screen updates in real-time without polling.
 */
class AttendanceScanEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $payload,  // pre-serialized — no Eloquent model on the wire
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('attendance'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'student.scanned';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
