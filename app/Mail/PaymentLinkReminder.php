<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\PaymentLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Patient-facing payment-link reminder email.
 *
 * Sent by PaymentLinkController::resendEmail when an agency user clicks
 * "Resend reminder" on a pending payment link. Uses the shared
 * emails.layout (agency primary/accent colors, logo, footer with
 * contact info) so each tenant's reminders look like THEIR brand
 * rather than generic Credentik.
 *
 * The view receives:
 *   - $link        — the PaymentLink model (amount, target_type, etc.)
 *   - $publicUrl   — fully-qualified V2 hash route the patient clicks
 *   - $agency      — the agency model, for branding/footer
 *   - $amount      — pre-formatted dollar string (number_format)
 *   - $descLabel   — human-readable target_type ("patient balance" etc.)
 *
 * From-header uses the agency's display name (e.g. "EnnHealth
 * Psychiatry <noreply@credentik.com>") so the patient sees the
 * provider's brand, not ours. reply_to is the agency's contact email
 * when present so patient questions reach them directly.
 */
class PaymentLinkReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PaymentLink $link,
        public string $publicUrl,
        public Agency $agency,
    ) {}

    public function envelope(): Envelope
    {
        $amount = number_format((float) $this->link->amount, 2);
        $agencyName = $this->agencyDisplayName();
        $fromAddress = config('mail.from.address', 'noreply@credentik.com');

        $envelope = new Envelope(
            from: new Address($fromAddress, $agencyName),
            subject: "Payment reminder from {$agencyName} — \${$amount}",
        );

        // Route patient replies to the agency if they've set a contact
        // email — otherwise drop reply-to entirely so it falls back to
        // the From header (Resend handles the bounce mailbox routing).
        if (!empty($this->agency->email)) {
            $envelope->replyTo = [new Address($this->agency->email, $agencyName)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        $amount = number_format((float) $this->link->amount, 2);
        $descLabel = match ($this->link->target_type) {
            'patient_statement' => 'patient statement',
            'invoice'           => 'invoice',
            default             => 'patient balance',
        };

        return new Content(
            view: 'emails.payment-link-reminder',
            with: [
                'link'      => $this->link,
                'publicUrl' => $this->publicUrl,
                'agency'    => $this->agency,
                'amount'    => $amount,
                'descLabel' => $descLabel,
                // Triggers the layout's branded header. "default" uses
                // the agency's primary_color which is what we want for
                // reminders — payment-received emails use 'success' etc.
                'headerType' => 'default',
            ],
        );
    }

    private function agencyDisplayName(): string
    {
        return $this->agency->company_display_name
            ?: $this->agency->name
            ?: 'Credentik';
    }
}
