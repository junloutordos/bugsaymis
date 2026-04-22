<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public Application $application;
    public string      $stage;
    public ?string     $remarks;

    public function __construct(Application $application, string $stage, ?string $remarks = null)
    {
        $this->application = $application;
        $this->stage       = $stage;
        $this->remarks     = $remarks;
    }

    public function build(): static
    {
        $labels = [
            'screening'  => 'Your application is now under Screening',
            'exam'       => 'You have been scheduled for the Written Exam',
            'interview'  => 'You have been scheduled for an Interview',
            'ranking'    => 'You are now at the Ranking stage',
            'selection'  => 'You have been shortlisted for Selection',
            'placement'  => 'Congratulations — You have been placed!',
            'rejected'   => 'Update on Your Application',
            'withdrawn'  => 'Your Application Has Been Withdrawn',
        ];

        $subject = $labels[$this->stage] ?? 'Update on Your Application';

        return $this->subject($subject)
                    ->view('emails.recruitment.application_status')
                    ->with([
                        'application' => $this->application,
                        'stage'       => $this->stage,
                        'remarks'     => $this->remarks,
                        'applicant'   => $this->application->applicant,
                        'position'    => $this->application->jobVacancy?->jobItem?->position_title ?? '—',
                        'subject'     => $subject,
                    ]);
    }
}
