<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $workRequest;
    public $status;
    public $reason;
    public $approver;

    public function __construct($workRequest, $status, $reason = null, $approver = null)
    {
        $this->workRequest = $workRequest;
        $this->status = $status;
        $this->reason = $reason;
        $this->approver = $approver;
    }

    public function build()
    {
        $statusLower = strtolower($this->status);
        if (str_contains($statusLower, 'approved')) {
            $subject = 'Your Work Request is Approved';
        } elseif (str_contains($statusLower, 'declined')) {
            $subject = 'Your Work Request is Declined';
        } else {
            // Neutral/informational statuses (e.g. progress updates) — don't
            // imply an approval decision that didn't happen.
            $subject = "Work Request Update — #{$this->workRequest->id}";
        }

        return $this->subject($subject)
                    ->view('emails.work_request_status')
                    ->with([
                        'request' => $this->workRequest,
                        'status' => $this->status,
                        'reason' => $this->reason,
                        'approver' => $this->approver,
                    ]);
    }
}
