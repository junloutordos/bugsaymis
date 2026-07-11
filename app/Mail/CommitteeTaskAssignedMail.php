<?php

namespace App\Mail;

use App\Models\CommitteeTask;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommitteeTaskAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CommitteeTask $task,
        public string $recipientName
    ) {}

    public function build()
    {
        return $this->subject('Committee Task Assigned: ' . $this->task->title)
                    ->view('emails.committee.task_assigned');
    }
}
