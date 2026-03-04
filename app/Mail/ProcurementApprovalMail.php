<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProcurementApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $procurement;
    public $approveUrl;
    public $declineUrl;

    public function __construct($procurement, $approveUrl, $declineUrl = null)
    {
        $this->procurement = $procurement;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('Purchase Request Approval')
                    ->view('emails.procurement_approval')
                    ->with([
                        'procurement' => $this->procurement,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                    ]);
    }
}
