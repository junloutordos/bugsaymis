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
    public $actor;

    public function __construct($vehicleRequest, $status, $reason = null, $actor = null)
    {
        $this->vehicleRequest = $vehicleRequest;
        $this->status = $status;
        $this->reason = $reason;
        $this->actor = $actor;
    }

    public function build()
    {
        $isApproved = str_contains($this->status, 'Approved');

        if ($this->actor === 'Division Chief') {
            $subject = $isApproved
                ? 'Vehicle Request has been approved by your Division Chief'
                : 'Vehicle Request has been declined by your Division Chief';
        } else {
            $subject = $isApproved ? 'Your Vehicle Request is Approved' : 'Your Vehicle Request is Declined';
        }

        return $this->subject($subject)
            ->view('emails.vehicle_request_status')
            ->with([
                'request' => $this->vehicleRequest,
                'status' => $this->status,
                'reason' => $this->reason,
            ]);
    }
}
