<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsultationScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $consult;

    public function __construct($consult)
    {
        $this->consult = $consult;
    }

    public function build()
    {
        return $this->subject('Your Consultation Appointment is Scheduled')
                    ->view('emails.consultation_scheduled')
                    ->with(['consult' => $this->consult]);
    }
}
