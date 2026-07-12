<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MobileEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $otp,
    ) {}

    public function build(): static
    {
        return $this->subject('Your AtlasGo Verification Code')
                    ->view('emails.mobile_otp')
                    ->with(['name' => $this->name, 'otp' => $this->otp]);
    }
}
