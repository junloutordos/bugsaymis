<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Document $document,
        public string   $recipientName,
    ) {}

    public function build(): static
    {
        $origin = $this->document->origin_type === 'external' ? 'External Incoming' : 'Internal';

        return $this->subject("[{$this->document->tracking_no}] New {$origin} Document — Action Required")
                    ->view('emails.document_status')
                    ->with([
                        'recipientName' => $this->recipientName,
                        'headline'      => 'New Document Requires Your Review',
                        'intro'         => "A new {$origin} document has been logged and is awaiting your review.",
                        'document'      => $this->document,
                        'extraRows'     => $this->document->origin_type === 'external' ? [
                            'Source Office' => $this->document->source_office ?? '—',
                            'Sender'        => $this->document->sender_name ?? '—',
                            'Date Received' => $this->document->date_received?->format('F j, Y') ?? '—',
                        ] : [],
                        'viewUrl'       => route('document-tracking.show', $this->document->id),
                        'btnLabel'      => 'Review Document',
                        'headerColor'   => '#6366f1',
                    ]);
    }
}
