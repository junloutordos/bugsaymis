<?php

namespace App\Mail;

use App\Models\ITJobRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DivisionChiefITJRApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $jobRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct(ITJobRequest $jobRequest, $approveUrl, $declineUrl)
    {
        $this->jobRequest = $jobRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject("IT Job Request Approval Needed: {$this->jobRequest->itjr_no}")
                    ->view('emails.itjr.division-chief-approval');
    }
}
