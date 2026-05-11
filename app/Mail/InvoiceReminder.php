<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public Agency $agency;

    public function __construct(public Invoice $invoice)
    {
        // Resolve the sending agency once so the Blade layout has the
        // branded header/footer fields available. Falls back to a bare
        // stub if the invoice somehow lost its tenant link — the layout
        // already handles a partial $agency gracefully.
        $this->agency = Agency::find($invoice->agency_id) ?? new Agency(['name' => 'Credentik']);
    }

    public function envelope(): Envelope
    {
        $number = $this->invoice->invoice_number ?: 'Invoice';
        $tenant = $this->agency->company_display_name ?: $this->agency->name;
        return new Envelope(subject: "Payment Reminder: {$number} — {$tenant}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice-reminder',
            with: ['agency' => $this->agency],
        );
    }
}
