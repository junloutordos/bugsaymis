<?php

namespace App\Mail;

use App\Models\Registrar\EnrollmentApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentApplicationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EnrollmentApplication $application,
    ) {}

    public function envelope(): Envelope
    {
        $action = $this->application->status === 'approved' ? 'Approved' : 'Not Approved';

        return new Envelope(
            subject: "Enrollment Application {$action} — {$this->application->reference_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.enrollment_decision',
        );
    }
}
