<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractAccepted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contract $contract,
        public Agency $agency,
        public string $signedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Contract Accepted — {$this->contract->title}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contract-accepted');
    }
}
