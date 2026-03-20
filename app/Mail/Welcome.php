<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Welcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Agency $agency,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to {$this->agency->name} on Credentik",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome');
    }
}
