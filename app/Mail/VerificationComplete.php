<?php

namespace App\Mail;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationComplete extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Agency $agency,
        public string $providerName,
        public string $credentialName,
        public ?string $verificationSource = 'Primary Source',
        public ?string $expirationDate = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Verification Complete: {$this->credentialName} — {$this->providerName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verification-complete');
    }
}
