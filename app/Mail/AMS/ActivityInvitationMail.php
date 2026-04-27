<?php

namespace App\Mail\AMS;

use App\Models\AMS\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ActivityInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Activity $activity,
        public readonly string   $recipientName,
    ) {}

    public function build(): static
    {
        $mail = $this
            ->subject("You're Invited: {$this->activity->title}")
            ->view('emails.ams.invitation');

        // Attach banner if exists
        if ($this->activity->banner && Storage::disk('public')->exists($this->activity->banner)) {
            $mail->attachFromStorageDisk(
                'public',
                $this->activity->banner,
                'Banner_' . str_replace(' ', '_', $this->activity->title) . '.' . pathinfo($this->activity->banner, PATHINFO_EXTENSION)
            );
        }

        // Attach Special Order if exists
        if ($this->activity->special_order && Storage::disk('public')->exists($this->activity->special_order)) {
            $mail->attachFromStorageDisk(
                'public',
                $this->activity->special_order,
                'SpecialOrder_' . str_replace(' ', '_', $this->activity->title) . '.' . pathinfo($this->activity->special_order, PATHINFO_EXTENSION)
            );
        }

        return $mail;
    }
}
