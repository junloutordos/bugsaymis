<?php

namespace App\Mail;

use App\Models\EmployeeIPCR;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IPCRPMTReturnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeIPCR $ipcr,
        public string $recipientName
    ) {}

    public function build()
    {
        return $this->subject('IPCR Returned by PMT for Revision — ' . ($this->ipcr->user->name ?? 'Employee'))
                    ->view('emails.ipcr.pmt_returned');
    }
}
