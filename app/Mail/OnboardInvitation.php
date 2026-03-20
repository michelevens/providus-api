<?php

namespace App\Mail;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Agency $agency,
        public string $onboardUrl,
        public int $expiresInDays = 7,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Complete Your Provider Profile — {$this->agency->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.onboard-invitation');
    }
}
