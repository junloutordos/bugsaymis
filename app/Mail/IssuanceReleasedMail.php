<?php

namespace App\Mail;

use App\Models\Issuance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class IssuanceReleasedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Issuance $issuance,
        public string   $recipientName,
    ) {}

    public function build(): static
    {
        $mail = $this
            ->subject("[{$this->issuance->control_number}] {$this->issuance->type_label}: {$this->issuance->title}")
            ->view('emails.issuance_released')
            ->with([
                'issuance'      => $this->issuance,
                'recipientName' => $this->recipientName,
                'viewUrl'       => route('issuances.show', $this->issuance->id),
                'verifyUrl'     => route('issuances.verify', $this->issuance->qr_token),
            ]);

        // Attach the generated PDF (generated in doRelease() before emails are sent)
        try {
            $pdf = Storage::disk('s3')->get('issuances/' . $this->issuance->control_number . '.pdf');
            $mail->attachData($pdf, $this->issuance->control_number . '.pdf', ['mime' => 'application/pdf']);
        } catch (\Throwable) {
            // PDF not ready or unavailable — email sent without attachment; user can download from app
        }

        return $mail;
    }
}
