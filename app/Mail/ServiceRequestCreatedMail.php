<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $serviceRequest;
    public $approveUrl;
    public $declineUrl;
    public $requestorName;
    public $requestorEmail;

    public function __construct($serviceRequest, $approveUrl = null, $declineUrl = null, $requestorName = null, $requestorEmail = null)
    {
        $this->serviceRequest = $serviceRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
        $this->requestorName = $requestorName;
        $this->requestorEmail = $requestorEmail;
    }

    public function build()
    {
        return $this->subject('Service Request Approval')
                    ->view('emails.service_request_created')
                    ->with([
                        'request' => $this->serviceRequest,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                        'requestor' => $this->requestorName,
                        'email' => $this->requestorEmail,
                    ]);
    }
}
