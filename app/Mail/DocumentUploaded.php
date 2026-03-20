<?php

namespace App\Mail;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentUploaded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Agency $agency,
        public string $providerName,
        public string $documentName,
        public ?string $documentType = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Document Uploaded: {$this->providerName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.document-uploaded');
    }
}
