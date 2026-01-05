<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VehicleRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $vehicleRequest;
    public $approveUrl;
    public $declineUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($vehicleRequest, $approveUrl, $declineUrl = null)
    {
        $this->vehicleRequest = $vehicleRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Vehicle Request Approval')
                    ->view('emails.vehicle_request_created')
                    ->with([
                        'request' => $this->vehicleRequest,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                    ]);
    }
}
