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
        $statusLower = strtolower($this->status ?? '');
        if (str_contains($statusLower, 'completed')) {
            $subject = 'Your Messengerial Request is now Completed';
        } elseif (str_contains($statusLower, 'approved')) {
            $subject = 'Your Messengerial Request is Approved';
        } else {
            $subject = 'Your Messengerial Request is Declined';
        }

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
