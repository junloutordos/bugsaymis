<?php

namespace App\Jobs\StudentAttendance;

use App\Models\Student;
use App\Services\StudentAttendance\SmsGateService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAttendanceSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int    $studentId,
        private readonly string $type,          // 'in' | 'out'
        private readonly string $scanTime,      // ISO 8601
        private readonly ?string $gateLocation,
    ) {}

    public function handle(SmsGateService $sms): void
    {
        $student = Student::with('parentContacts')->find($this->studentId);

        if (! $student) {
            Log::warning('SendAttendanceSmsJob: student not found.', ['student_id' => $this->studentId]);
            return;
        }

        $recipients = $student->parentContacts
            ->filter(fn ($c) => $c->notify_sms && ! empty($c->mobile_phone));

        if ($recipients->isEmpty()) return;

        $message = $this->buildMessage($student);

        foreach ($recipients as $contact) {
            $sent = $sms->send($contact->mobile_phone, $message);

            Log::info('SendAttendanceSmsJob: SMS ' . ($sent ? 'sent' : 'failed') . '.', [
                'student'  => $student->full_name,
                'type'     => $this->type,
                'contact'  => $contact->name,
                'phone'    => substr($contact->mobile_phone, 0, 6) . '…',
            ]);
        }
    }

    private function buildMessage(Student $student): string
    {
        $time      = Carbon::parse($this->scanTime)->setTimezone('Asia/Manila')->format('g:i A');
        $firstName = $student->firstname;
        $action    = $this->type === 'in' ? 'has arrived at school' : 'has left school';
        $gate      = $this->gateLocation ? " ({$this->gateLocation})" : '';

        return "[PSHS-CRC] {$firstName} {$action}{$gate} at {$time}.";
    }
}
