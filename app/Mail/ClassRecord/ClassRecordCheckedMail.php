<?php

namespace App\Mail\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClassRecordCheckedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClassRecord $classRecord,
        public User        $checkedBy,
        public ?User        $recipient = null,
    ) {}

    public function build(): static
    {
        $subject = $this->classRecord->subject_name;
        $section = $this->classRecord->year_level_section;
        // Defaults to the primary teacher for backward compatibility — every
        // existing call site that doesn't pass $recipient keeps working
        // exactly as before. Co-teacher notifications pass their own User.
        $recipient = $this->recipient ?? $this->classRecord->teacher;

        return $this->subject("Class Record Checked — {$subject} ({$section})")
                    ->view('emails.document_status')
                    ->with([
                        'recipientName' => $recipient->name,
                        'headline'      => 'Your Class Record Has Been Checked',
                        'intro'         => "Your class record for <strong>{$subject}</strong> ({$section}) has been reviewed and marked as <strong>Checked</strong> by <strong>{$this->checkedBy->name}</strong>.",
                        'extraRows'     => [
                            'Subject'    => $subject,
                            'Section'    => $section,
                            'School Year'=> $this->classRecord->school_year,
                            'Checked By' => $this->checkedBy->name,
                            'Checked At' => now()->format('F j, Y g:i A'),
                        ],
                        'viewUrl'       => route('class-records.page.show', $this->classRecord),
                        'btnLabel'      => 'View Class Record',
                        'headerColor'   => '#6366f1',
                    ]);
    }
}
