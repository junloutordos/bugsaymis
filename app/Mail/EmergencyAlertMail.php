<?php

namespace App\Mail;

use App\Models\Sos\EmergencyAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmergencyAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EmergencyAlert $alert,
        public readonly ?User $recipient,
    ) {}

    public function build()
    {
        return $this->subject("PSHS-CRC Emergency Alert — {$this->alert->title}")
            ->view('emails.emergency_alert')
            ->with(['alert' => $this->alert, 'recipient' => $this->recipient]);
    }
}
