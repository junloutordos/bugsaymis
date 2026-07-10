<?php

namespace App\Mail;

use App\Models\Administration\CoaCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CoaIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CoaCertificate $certificate,
    ) {}

    public function build(): static
    {
        $mail = $this
            ->subject("[{$this->certificate->control_number}] Certificate of Appearance — PSHS-CRC")
            ->view('emails.coa_issued')
            ->with([
                'certificate' => $this->certificate,
                'visit'       => $this->certificate->visit,
                'verifyUrl'   => route('coa.verify', $this->certificate->qr_token),
            ]);

        // Attach the generated PDF (generated in ProcessCoaIssue before emails are sent)
        try {
            $pdf = Storage::disk('s3')->get('coa/' . $this->certificate->control_number . '.pdf');
            $mail->attachData($pdf, $this->certificate->control_number . '.pdf', ['mime' => 'application/pdf']);
        } catch (\Throwable) {
            // PDF unavailable — email still goes out with the verify link
        }

        return $mail;
    }
}
