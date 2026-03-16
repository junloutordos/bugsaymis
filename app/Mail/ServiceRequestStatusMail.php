<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $serviceRequest;
    public $status;
    public $reason;
    public $approver;
    public $requestorName;
    public $requestorEmail;

    public function __construct($serviceRequest, $status, $reason = null, $approver = null, $requestorName = null, $requestorEmail = null)
    {
        $this->serviceRequest = $serviceRequest;
        $this->status = $status;
        $this->reason = $reason;
        $this->approver = $approver;
        $this->requestorName = $requestorName;
        $this->requestorEmail = $requestorEmail;
    }

    public function build()
    {
        $isApproved = str_contains(strtolower($this->status), 'approved');
        $subject = $isApproved ? 'Your Service Request is Approved' : 'Your Service Request is Declined';

        return $this->subject($subject)
                    ->view('emails.service_request_status')
                    ->with([
                        'request' => $this->serviceRequest,
                        'status' => $this->status,
                        'reason' => $this->reason,
                        'approver' => $this->approver,
                        'requestor' => $this->requestorName,
                        'email' => $this->requestorEmail,
                    ]);
    }
}
