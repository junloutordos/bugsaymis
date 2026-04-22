<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentAttendanceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $parentName,
        public readonly string $studentName,
        public readonly string $scanType,       // 'in' | 'out'
        public readonly string $scanTimeFormatted,
        public readonly string $gateLocation,
    ) {}

    public function build(): static
    {
        $typeLabel = $this->scanType === 'in' ? 'arrived at school (Time In)' : 'left school (Time Out)';

        return $this->subject("Your child {$this->studentName} has {$typeLabel}")
                    ->view('emails.student_attendance')
                    ->with([
                        'parentName'       => $this->parentName,
                        'studentName'      => $this->studentName,
                        'scanType'         => $this->scanType,
                        'scanTypeLabel'    => $this->scanType === 'in' ? 'TIME IN' : 'TIME OUT',
                        'scanTime'         => $this->scanTimeFormatted,
                        'gateLocation'     => $this->gateLocation,
                    ]);
    }
}
