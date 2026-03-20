<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Followup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FollowupReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Followup $followup,
        public Agency $agency,
        public string $providerName,
        public string $payerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Follow-Up Due: {$this->providerName} — {$this->payerName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.followup-reminder');
    }
}
