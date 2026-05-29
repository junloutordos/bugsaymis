<?php

namespace App\Mail;

use App\Models\ITJobRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ITJRStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $jobRequest;
    public $status;
    public $reason;
    public $approverRole;

    public function __construct(ITJobRequest $jobRequest, $status, $reason = null, $approverRole = null)
    {
        $this->jobRequest = $jobRequest;
        $this->status = $status;
        $this->reason = $reason;
        $this->approverRole = $approverRole;
    }

    public function build()
    {
        return $this->subject("IT Job Request Status Update: {$this->jobRequest->itjr_no}")
                    ->view('emails.itjr.status');
    }
}
