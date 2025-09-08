<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ITJobRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $jobRequest;

    public function __construct(ITJobRequest $jobRequest)
    {
        $this->jobRequest = $jobRequest;
    }

    public function build()
    {
        return $this->subject('New IT Job Request Submitted')
            ->view('emails.jobrequest_created')
            ->with([
                'jobRequest' => $this->jobRequest,
            ]);
    }
    
    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'I T Job Request Created Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'view.name',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
