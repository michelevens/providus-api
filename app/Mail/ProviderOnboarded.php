<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Provider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProviderOnboarded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Provider $provider,
        public Agency $agency,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome Aboard — {$this->agency->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.provider-onboarded');
    }
}
