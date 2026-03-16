<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VehicleRequestGSUHeadMail extends Mailable
{
    use Queueable, SerializesModels;

    public $vehicleRequest;

    public function __construct($vehicleRequest)
    {
        $this->vehicleRequest = $vehicleRequest;
    }

    public function build()
    {
        return $this->subject('GSU Head Action Required: Vehicle Request')
            ->view('emails.vehicle_request_gsu_head')
            ->with([
                'request' => $this->vehicleRequest,
            ]);
    }
}
