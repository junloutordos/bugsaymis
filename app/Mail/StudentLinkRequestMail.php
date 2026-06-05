<?php

namespace App\Mail;

use App\Models\StudentLinkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentLinkRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly StudentLinkRequest $linkRequest,
        public readonly string $parentName,
    ) {}

    public function build(): static
    {
        $confirmUrl = url("/student-attendance/link/{$this->linkRequest->token}/confirm");
        $denyUrl    = url("/student-attendance/link/{$this->linkRequest->token}/deny");

        return $this->subject("MyPisay: A parent requests to be linked to your account")
                    ->view('emails.student_link_request')
                    ->with([
                        'parentName'   => $this->parentName,
                        'relationship' => ucfirst($this->linkRequest->relationship),
                        'confirmUrl'   => $confirmUrl,
                        'denyUrl'      => $denyUrl,
                        'expiresAt'    => $this->linkRequest->expires_at->format('M d, Y h:i A'),
                    ]);
    }
}
