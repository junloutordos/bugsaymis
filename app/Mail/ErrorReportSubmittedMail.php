<?php

namespace App\Mail;

use App\Models\ErrorReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ErrorReportSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ErrorReport $report) {}

    public function build(): static
    {
        return $this
            ->subject("[{$this->report->report_no}] New Error Report: {$this->report->title}")
            ->view('emails.error_report_submitted');
    }
}
