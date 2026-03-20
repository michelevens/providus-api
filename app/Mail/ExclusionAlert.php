<?php

namespace App\Mail;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExclusionAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Agency $agency,
        public string $providerName,
        public ?string $npi = null,
        public ?string $source = 'OIG/SAM',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠ Exclusion Alert: {$this->providerName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.exclusion-alert');
    }
}
