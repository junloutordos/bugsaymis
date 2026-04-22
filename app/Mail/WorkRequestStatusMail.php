<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $workRequest;
    public $status;
    public $reason;
    public $approver;

    public function __construct($workRequest, $status, $reason = null, $approver = null)
    {
        $this->workRequest = $workRequest;
        $this->status = $status;
        $this->reason = $reason;
        $this->approver = $approver;
    }

    public function build()
    {
        $isApproved = str_contains(strtolower($this->status), 'approved');
        $subject = $isApproved ? 'Your Work Request is Approved' : 'Your Work Request is Declined';

        return $this->subject($subject)
                    ->view('emails.work_request_status')
                    ->with([
                        'request' => $this->workRequest,
                        'status' => $this->status,
                        'reason' => $this->reason,
                        'approver' => $this->approver,
                    ]);
    }
}
