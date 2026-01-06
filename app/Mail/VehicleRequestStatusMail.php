<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VehicleRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $vehicleRequest;
    public $status;
    public $reason;

    public function __construct($vehicleRequest, $status, $reason = null)
    {
        $this->vehicleRequest = $vehicleRequest;
        $this->status = $status;
        $this->reason = $reason;
    }

    public function build()
    {
        $isApproved = str_contains($this->status, 'Approved');
        $subject = $isApproved ? 'Your Vehicle Request is Approved' : 'Your Vehicle Request is Declined';
        return $this->subject($subject)
            ->view('emails.vehicle_request_status')
            ->with([
                'request' => $this->vehicleRequest,
                'status' => $this->status,
                'reason' => $this->reason,
            ]);
    }
}
