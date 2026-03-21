<?php

namespace App\Mail;

use App\Models\Placement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicationPlacedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Placement $placement;

    public function __construct(Placement $placement)
    {
        $this->placement = $placement;
    }

    public function build(): static
    {
        return $this->subject('Congratulations — Your Placement Has Been Confirmed!')
                    ->view('emails.recruitment.application_placed')
                    ->with([
                        'placement'  => $this->placement,
                        'applicant'  => $this->placement->application?->applicant,
                        'position'   => $this->placement->application?->jobVacancy?->jobItem?->position_title ?? '—',
                        'office'     => $this->placement->office?->name ?? '—',
                        'start_date' => $this->placement->start_date
                            ? \Carbon\Carbon::parse($this->placement->start_date)->format('F j, Y')
                            : '—',
                        'end_date'   => $this->placement->end_date
                            ? \Carbon\Carbon::parse($this->placement->end_date)->format('F j, Y')
                            : null,
                        'remarks'    => $this->placement->remarks,
                    ]);
    }
}
