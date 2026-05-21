<?php

namespace App\Mail;

use App\Models\DocumentRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentReturnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DocumentRouting $routing) {}

    public function build(): static
    {
        $doc = $this->routing->document;

        return $this->subject("[{$doc->tracking_no}] Document Returned by {$this->routing->receiver->name}")
                    ->view('emails.document_status')
                    ->with([
                        'recipientName' => $this->routing->sender->name,
                        'headline'      => 'Document Has Been Returned',
                        'intro'         => "<strong>{$this->routing->receiver->name}</strong> has returned this document to you. Please review the reason below.",
                        'document'      => $doc,
                        'extraRows'     => [
                            'Returned By'   => $this->routing->receiver->name,
                            'Return Reason' => $this->routing->return_reason ?? '—',
                            'Returned At'   => $this->routing->returned_at?->format('F j, Y g:i A') ?? now()->format('F j, Y g:i A'),
                        ],
                        'viewUrl'       => route('document-tracking.show', $doc->id),
                        'btnLabel'      => 'View Document',
                        'headerColor'   => '#ef4444',
                    ]);
    }
}
