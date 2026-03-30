<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacilityRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $facilityRequest;
    public $status;
    public $reason;
    public $approver;

    public function __construct($facilityRequest, $status, $reason = null, $approver = null)
    {
        $this->facilityRequest = $facilityRequest;
        $this->status = $status;
        $this->reason = $reason;
        $this->approver = $approver;
    }

    public function build()
    {
        $isApproved = str_contains(strtolower($this->status), 'approved');
        $subject = $isApproved ? 'Your Facility Request is Approved' : 'Your Facility Request is Declined';

        return $this->subject($subject)
                    ->view('emails.facility_request_status')
                    ->with([
                        'request' => $this->facilityRequest,
                        'status' => $this->status,
                        'reason' => $this->reason,
                        'approver' => $this->approver,
                    ]);
    }
}
