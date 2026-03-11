<?php

namespace App\Mail;

use App\Models\EmployeeIPCR;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IPCRTargetsApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeIPCR $ipcr,
        public string $recipientName
    ) {}

    public function build()
    {
        return $this->subject('Your IPCR Targets Have Been Approved')
                    ->view('emails.ipcr.targets_approved');
    }
}
