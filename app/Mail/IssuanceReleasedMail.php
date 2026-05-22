<?php

namespace App\Mail;

use App\Models\Issuance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IssuanceReleasedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Issuance $issuance,
        public string   $recipientName,
    ) {}

    public function build(): static
    {
        return $this
            ->subject("[{$this->issuance->control_number}] {$this->issuance->type_label}: {$this->issuance->title}")
            ->view('emails.issuance_released')
            ->with([
                'issuance'      => $this->issuance,
                'recipientName' => $this->recipientName,
                'viewUrl'       => route('issuances.show', $this->issuance->id),
                'verifyUrl'     => route('issuances.verify', $this->issuance->qr_token),
            ]);
    }
}
