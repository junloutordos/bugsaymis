<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkRequestAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $workRequest;

    public function __construct($workRequest)
    {
        $this->workRequest = $workRequest;
    }

    public function build()
    {
        return $this->subject('New Work Request Assigned')
                    ->view('emails.work_request_assigned')
                    ->with([
                        'request' => $this->workRequest,
                    ]);
    }
}
