<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MessengerialRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $requestModel;
    public $status;
    public $reason;
    public $approver;

    public function __construct($requestModel, $status, $reason = null, $approver = null)
    {
        $this->requestModel = $requestModel;
        $this->status = $status;
        $this->reason = $reason;
        $this->approver = $approver;
    }

    public function build()
    {
        $isApproved = str_contains(strtolower($this->status), 'approved');
        $subject = $isApproved ? 'Your Messengerial Request is Approved' : 'Your Messengerial Request is Declined';

        return $this->subject($subject)
                    ->view('emails.messengerial_request_status')
                    ->with([
                        'request' => $this->requestModel,
                        'status' => $this->status,
                        'reason' => $this->reason,
                        'approver' => $this->approver,
                    ]);
    }
}
