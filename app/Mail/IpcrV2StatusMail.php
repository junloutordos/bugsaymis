<?php

namespace App\Mail;

use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IpcrV2StatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public IpcrV2Record $ipcr,
        public User $recipient,
        public string $newStatus,
        public ?string $remarks = null,
    ) {}

    public function build()
    {
        return $this->subject("IPCR V2 — {$this->newStatus}")
            ->view('emails.ipcr-v2.status_changed');
    }
}
