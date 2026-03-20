<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contract $contract,
        public Agency $agency,
        public string $recipientName,
        public string $contractUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Contract Ready for Review — {$this->agency->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contract-sent');
    }
}
