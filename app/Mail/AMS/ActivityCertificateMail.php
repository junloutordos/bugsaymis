<?php

namespace App\Mail\AMS;

use App\Models\AMS\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ActivityCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Activity $activity,
        public readonly string   $recipientName,
        public readonly string   $certificateStoragePath,
    ) {}

    public function build(): static
    {
        $filename = 'Certificate_' . str_replace(' ', '_', $this->activity->title) . '.pdf';

        return $this
            ->subject("Certificate of Participation — {$this->activity->title}")
            ->view('emails.ams.certificate')
            ->attachFromStorageDisk('public', $this->certificateStoragePath, $filename);
    }
}
