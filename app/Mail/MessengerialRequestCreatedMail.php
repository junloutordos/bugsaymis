<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MessengerialRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $messengerialRequest;
    public $approveUrl;
    public $declineUrl;

    public function __construct($messengerialRequest, $approveUrl = null, $declineUrl = null)
    {
        $this->messengerialRequest = $messengerialRequest;
        $this->approveUrl = $approveUrl;
        $this->declineUrl = $declineUrl;
    }

    public function build()
    {
        return $this->subject('Messengerial Request Approval')
                    ->view('emails.messengerial_request_created')
                    ->with([
                        'request' => $this->messengerialRequest,
                        'approveUrl' => $this->approveUrl,
                        'declineUrl' => $this->declineUrl,
                    ]);
    }
}
