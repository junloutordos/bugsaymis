<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultationCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $consult;

    public function __construct($consult)
    {
        $this->consult = $consult;
    }

    public function build()
    {
        return $this->subject('New Consultation Request — Action Required')
                    ->view('emails.consultation_created')
                    ->with(['consult' => $this->consult]);
    }
}
