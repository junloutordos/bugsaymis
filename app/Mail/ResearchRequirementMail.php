<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResearchRequirementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $headerTitle,
        public string $lead,
        public array $details = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    public function build()
    {
        return $this->subject($this->headerTitle)
            ->view('emails.research_requirement')
            ->with([
                'recipientName' => $this->recipientName,
                'headerTitle'   => $this->headerTitle,
                'lead'          => $this->lead,
                'details'       => $this->details,
                'actionUrl'     => $this->actionUrl,
                'actionLabel'   => $this->actionLabel,
            ]);
    }
}
