<?php

namespace App\Mail;

use App\Models\Registrar\EnrollmentApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EnrollmentApplication $application,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Enrollment Application Received — {$this->application->reference_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enrollment_received',
        );
    }
}
