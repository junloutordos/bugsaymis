<?php

namespace App\Mail;

use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SosAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SosAlert $alert,
        public readonly ?User $recipient,
    ) {}

    public function build()
    {
        $label = ucfirst(str_replace('_', ' ', $this->alert->alert_type));

        return $this->subject("PSHS-CRC SOS Alert — {$label}")
            ->view('emails.sos_alert')
            ->with(['alert' => $this->alert, 'recipient' => $this->recipient]);
    }
}
