<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VehicleRequestOCDMail extends Mailable
{
    use Queueable, SerializesModels;

    public $vehicleRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($vehicleRequest, $approveUrl, $declineUrl = null)
    {
        $this->vehicleRequest = $vehicleRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('OCD Action Required: Vehicle Request')
            ->view('emails.vehicle_request_ocd_notification')
            ->with([
                'request' => $this->vehicleRequest,
                'approveUrl' => $this->approveUrl,
                'declineUrl' => $this->declineUrl,
            ]);
    }
}
