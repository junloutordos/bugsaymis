<?php

namespace App\Mail;

use App\Models\EmployeeIPCR;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IPCRApprovedByPMTMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmployeeIPCR $ipcr,
        public string $recipientName
    ) {}

    public function build()
    {
        return $this->subject('IPCR Approved by PMT — Pending Director Signature')
                    ->view('emails.ipcr.approved_by_pmt');
    }
}
