<?php

namespace App\Mail;

use App\Models\DocumentRouting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentRoutedMail extends Mailable
{
    use Queueable, SerializesModels;

    public DocumentRouting $routing;
    public string $actionType; // 'new' | 'forwarded'

    public function __construct(DocumentRouting $routing, string $actionType = 'new')
    {
        $this->routing = $routing;
        $this->actionType = $actionType;
    }

    public function build(): static
    {
        $trackingNo = $this->routing->document->tracking_no;
        $subject = $this->actionType === 'new'
            ? "New Document Routed to You: [{$trackingNo}]"
            : "Document Forwarded to You: [{$trackingNo}]";

        return $this->subject($subject)
                    ->view('emails.document_routed')
                    ->with([
                        'routing'    => $this->routing,
                        'actionType' => $this->actionType,
                        'viewUrl'    => route('document-tracking.show', $this->routing->document_id),
                    ]);
    }
}
