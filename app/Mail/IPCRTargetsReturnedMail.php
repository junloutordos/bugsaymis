<?php

namespace App\Mail;

use App\Models\EmployeeIPCR;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IPCRTargetsReturnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeIPCR $ipcr,
        public string $recipientName
    ) {}

    public function build()
    {
        return $this->subject('Your IPCR Targets Have Been Returned for Revision')
                    ->view('emails.ipcr.targets_returned');
    }
}
