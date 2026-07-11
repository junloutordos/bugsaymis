<?php

namespace App\Mail;

use App\Models\EmployeeIPCR;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IPCRReadyForEndorsementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeIPCR $ipcr,
        public string $recipientName,
        public User $rater
    ) {}

    public function build()
    {
        return $this->subject('IPCR Ready for Endorsement to PMT')
                    ->view('emails.ipcr.ready_for_endorsement');
    }
}
