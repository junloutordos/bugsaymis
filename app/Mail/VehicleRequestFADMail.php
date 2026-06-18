<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VehicleRequestFADMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly mixed $vehicleRequest,
        public readonly string $approveUrl,
        public readonly ?string $declineUrl = null,
    ) {}

    public function build()
    {
        return $this->subject('FAD Action Required: Vehicle Request')
            ->view('emails.vehicle_request_fad_notification')
            ->with([
                'request'    => $this->vehicleRequest,
                'approveUrl' => $this->approveUrl,
                'declineUrl' => $this->declineUrl,
            ]);
    }
}
