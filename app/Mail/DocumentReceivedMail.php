<?php

namespace App\Mail;

use App\Models\DocumentRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DocumentRouting $routing) {}

    public function build(): static
    {
        $doc = $this->routing->document;

        return $this->subject("[{$doc->tracking_no}] Document Acknowledged by {$this->routing->receiver->name}")
                    ->view('emails.document_status')
                    ->with([
                        'recipientName' => $this->routing->sender->name,
                        'headline'      => 'Document Has Been Acknowledged',
                        'intro'         => "<strong>{$this->routing->receiver->name}</strong> has acknowledged receipt of the document you sent.",
                        'document'      => $doc,
                        'extraRows'     => [
                            'Acknowledged By' => $this->routing->receiver->name,
                            'Acknowledged At' => $this->routing->received_at?->format('F j, Y g:i A') ?? now()->format('F j, Y g:i A'),
                            'Step'            => "Step #{$this->routing->sequence}",
                        ],
                        'viewUrl'       => route('document-tracking.show', $doc->id),
                        'btnLabel'      => 'View Document',
                        'headerColor'   => '#10b981',
                    ]);
    }
}
