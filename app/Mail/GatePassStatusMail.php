<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GatePassStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $gatepass;
    public $status;
    public $reason;
    public $actor;
    public $approveUrl;
    public $declineUrl;

    public function __construct($gatepass, $status, $reason = null, $actor = null, $approveUrl = null, $declineUrl = null)
    {
        $this->gatepass = $gatepass;
        $this->status = $status;
        $this->reason = $reason;
        $this->actor = $actor;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        $isApproved = str_contains(strtolower($this->status), 'approved');
        if ($this->actor === 'Division Chief') {
            $subject = $isApproved ? 'Gate Pass Approved by your Division Chief' : 'Gate Pass Declined by your Division Chief';
        } else {
            $subject = $isApproved ? 'Your Gate Pass is Approved' : 'Your Gate Pass is Declined';
        }

        return $this->subject($subject)
                    ->view('emails.gatepass_status')
                    ->with([
                        'gatepass' => $this->gatepass,
                        'status' => $this->status,
                        'reason' => $this->reason,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                    ]);
    }
}
