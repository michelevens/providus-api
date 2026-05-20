<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to org/provider recipient when an agency creates a
 * document request. Tokenized upload URL — recipient can fulfill
 * without logging in. Branded with the agency's primary color +
 * name (Credentik as platform footer).
 */
class DocumentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DocumentRequest $req,
        public string $publicUrl,
        public Agency $agency,
    ) {}

    public function envelope(): Envelope
    {
        $agencyName = $this->agency->company_display_name ?: ($this->agency->name ?: 'Credentik');
        $fromAddress = config('mail.from.address', 'noreply@credentik.com');
        $itemCount = count($this->req->items ?? []);

        $envelope = new Envelope(
            from: new Address($fromAddress, $agencyName),
            subject: $itemCount === 1
                ? "Document request from {$agencyName}"
                : "{$itemCount} document requests from {$agencyName}",
        );
        if (!empty($this->agency->email)) {
            $envelope->replyTo = [new Address($this->agency->email, $agencyName)];
        }
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-request',
            with: [
                'req'        => $this->req,
                'publicUrl'  => $this->publicUrl,
                'agency'     => $this->agency,
                'headerType' => 'default',
            ],
        );
    }
}
