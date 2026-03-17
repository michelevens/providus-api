<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        $number = $this->invoice->invoice_number ?: 'Invoice';
        return new Envelope(subject: "Payment Reminder: {$number}");
    }

    public function build()
    {
        $inv = $this->invoice;
        $number = $inv->invoice_number ?: 'N/A';
        $amount = number_format($inv->total, 2);
        $due = $inv->due_date?->format('M d, Y') ?? 'N/A';
        $client = e($inv->client_name);

        return $this->html("
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h2 style='color:#1a56db;'>Invoice Payment Reminder</h2>
            <p>Dear {$client},</p>
            <p>This is a friendly reminder that the following invoice is due:</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                <tr><td style='padding:8px;border:1px solid #e5e7eb;font-weight:600;'>Invoice #</td><td style='padding:8px;border:1px solid #e5e7eb;'>{$number}</td></tr>
                <tr><td style='padding:8px;border:1px solid #e5e7eb;font-weight:600;'>Amount Due</td><td style='padding:8px;border:1px solid #e5e7eb;'>\${$amount}</td></tr>
                <tr><td style='padding:8px;border:1px solid #e5e7eb;font-weight:600;'>Due Date</td><td style='padding:8px;border:1px solid #e5e7eb;'>{$due}</td></tr>
            </table>
            <p>Please arrange payment at your earliest convenience.</p>
            <p style='color:#6b7280;font-size:12px;margin-top:24px;'>— Credentik</p>
        </div>");
    }
}
