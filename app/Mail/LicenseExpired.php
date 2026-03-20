<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LicenseExpired extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public License $license,
        public Agency $agency,
        public string $providerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠ License Expired: {$this->providerName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.license-expired');
    }
}
