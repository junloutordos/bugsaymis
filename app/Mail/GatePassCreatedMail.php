<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GatePassCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $gatepass;
    public $approveUrl;
    public $declineUrl;

    public function __construct($gatepass, $approveUrl, $declineUrl = null)
    {
        $this->gatepass = $gatepass;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('Gate Pass Approval')
                    ->view('emails.gatepass_created')
                    ->with([
                        'gatepass' => $this->gatepass,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                    ]);
    }
}
