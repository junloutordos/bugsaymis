<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MessengerialRequestRecordsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $requestModel;
    public $processUrl;

    public function __construct($requestModel, $processUrl = null)
    {
        $this->requestModel = $requestModel;
        $this->processUrl = $processUrl;
    }

    public function build()
    {
        return $this->subject('Messengerial Request Needs Processing')
                    ->view('emails.messengerial_request_records')
                    ->with([
                        'request' => $this->requestModel,
                        'processUrl' => $this->processUrl,
                    ]);
    }
}
