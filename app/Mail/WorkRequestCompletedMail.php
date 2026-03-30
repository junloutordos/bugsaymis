<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkRequestCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $workRequest;

    public function __construct($workRequest)
    {
        $this->workRequest = $workRequest;
    }

    public function build()
    {
        return $this->subject('Your Work Request has been Completed')
                    ->view('emails.work_request_completed')
                    ->with(['request' => $this->workRequest]);
    }
}
