<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GatePassDeclinedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $gatepass;
    public $reason;

    public function __construct($gatepass, $reason = null)
    {
        $this->gatepass = $gatepass;
        $this->reason = $reason;
    }

    public function build()
    {
        $subject = 'Your Gate Pass Request was Declined';
        return $this->subject($subject)
                    ->view('emails.gatepass_declined')
                    ->with([
                        'gatepass' => $this->gatepass,
                        'reason' => $this->reason,
                    ]);
    }
}
