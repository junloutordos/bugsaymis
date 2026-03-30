<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkRequestForAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $workRequest;
    public $chiefId;

    public function __construct($workRequest, $chiefId = null)
    {
        $this->workRequest = $workRequest;
        $this->chiefId = $chiefId;
    }

    public function build()
    {
        return $this->subject('Work Request Ready for Assignment')
                    ->view('emails.work_request_for_assignment')
                    ->with([
                        'request' => $this->workRequest,
                    ]);
    }
}
