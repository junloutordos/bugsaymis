<?php

namespace App\Mail\AMS;

use App\Models\AMS\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivityEvaluationInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Activity $activity,
        public readonly string   $recipientName,
        public readonly string   $evaluationUrl,
    ) {}

    public function build(): static
    {
        return $this
            ->subject("Please Evaluate: {$this->activity->title}")
            ->view('emails.ams.evaluation_invite');
    }
}
