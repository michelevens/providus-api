<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $planName,
        public string $billingCycle = 'monthly',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Subscription Active — Credentik " . ucfirst($this->planName),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-welcome');
    }
}
