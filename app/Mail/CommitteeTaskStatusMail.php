<?php

namespace App\Mail;

use App\Models\CommitteeTask;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommitteeTaskStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CommitteeTask $task,
        public string $recipientName,
        public User $actor
    ) {}

    public function build()
    {
        $label = $this->task->status === 'done' ? 'Done' : 'Stuck';

        return $this->subject("Committee Task {$label}: " . $this->task->title)
                    ->view('emails.committee.task_status');
    }
}
