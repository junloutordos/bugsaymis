<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkRequestFADApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $workRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($workRequest, $approveUrl = null, $declineUrl = null)
    {
        $this->workRequest = $workRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('Work Request — FAD Approval Needed')
                    ->view('emails.work_request_fad_approval')
                    ->with([
                        'request' => $this->workRequest,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                    ]);
    }
}
