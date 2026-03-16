<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvite extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Agency $agency,
        public string $inviteUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to {$this->agency->name} on Credentik",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invite',
        );
    }
}
