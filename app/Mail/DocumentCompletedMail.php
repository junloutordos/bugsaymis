<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public string   $recipientName,
        public string   $completedBy,
    ) {}

    public function build(): static
    {
        return $this->subject("[{$this->document->tracking_no}] Document Completed and Filed")
                    ->view('emails.document_status')
                    ->with([
                        'recipientName' => $this->recipientName,
                        'headline'      => 'Document Completed and Filed',
                        'intro'         => "Document <strong>{$this->document->tracking_no}</strong> has been marked as completed and filed by <strong>{$this->completedBy}</strong>.",
                        'document'      => $this->document,
                        'extraRows'     => [
                            'Completed By' => $this->completedBy,
                            'Completed At' => $this->document->completed_at?->format('F j, Y g:i A') ?? now()->format('F j, Y g:i A'),
                        ],
                        'viewUrl'       => route('document-tracking.show', $this->document->id),
                        'btnLabel'      => 'View Document Record',
                        'headerColor'   => '#0ea5e9',
                    ]);
    }
}
