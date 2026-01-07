<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacilityRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $facilityRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($facilityRequest, $approveUrl = null, $declineUrl = null)
    {
        $this->facilityRequest = $facilityRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('Facility Request Approval')
                    ->view('emails.facility_request_created')
                    ->with([
                        'request' => $this->facilityRequest,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                    ]);
    }
}
