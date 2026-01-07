<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacilityRequestOCDMail extends Mailable
{
    use Queueable, SerializesModels;

    public $facilityRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($facilityRequest, $approveUrl, $declineUrl = null)
    {
        $this->facilityRequest = $facilityRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('OCD Action Required: Facility Request')
            ->view('emails.facility_request_ocd_notification')
            ->with([
                'request' => $this->facilityRequest,
                'approveUrl' => $this->approveUrl,
                'declineUrl' => $this->declineUrl,
            ]);
    }
}
