<?php

namespace App\Mail;

use App\Models\Committee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * One shared Mailable for every committee lifecycle/roster email
 * (assignment added/removed, role changed, revoked, amended) — mirrors
 * CommitteeTaskAssignedMail's minimal-Mailable pattern but is parameterized
 * instead of duplicated per event, since all these emails share the same
 * "committee + role + load units + one extra fact" shape.
 */
class CommitteeAssignmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Committee $committee,
        public string $recipientName,
        public string $subjectLine,
        public string $headerTitle,
        public string $lead,
        public ?string $role = null,
        public ?float $loadUnits = null,
        public ?string $extraLabel = null,
        public ?string $extraValue = null,
    ) {}

    public function build()
    {
        return $this->subject($this->subjectLine)
                    ->view('emails.committee.assignment_notice');
    }
}
