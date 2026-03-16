<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestFADMail extends Mailable
{
    use Queueable, SerializesModels;

    public $serviceRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($serviceRequest, $approveUrl, $declineUrl = null)
    {
        $this->serviceRequest = $serviceRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('FAD Chief Action Required: Service Request')
            ->view('emails.service_request_created')
            ->with([
                'request' => $this->serviceRequest,
                'approveUrl' => $this->approveUrl,
                'declineUrl' => $this->declineUrl,
            ]);
    }
}
