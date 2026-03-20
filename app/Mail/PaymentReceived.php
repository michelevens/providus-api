<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Agency $agency,
        public float $amountPaid,
        public float $remainingBalance = 0,
    ) {}

    public function envelope(): Envelope
    {
        $number = $this->invoice->invoice_number ?: 'Invoice';
        return new Envelope(subject: "Payment Received: {$number}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-received');
    }
}
