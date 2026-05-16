<?php

namespace App\Mail;

use App\Models\PatientStatement;
use App\Services\BrandingResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatientStatementEmail extends Mailable
{
    use Queueable, SerializesModels;

    /** Tone bucket — drives subject line + intro copy in the view. */
    public string $tone;

    /** Resolved brand for THIS statement — practice override if set,
     *  agency fallback otherwise. Blade template still receives an
     *  `$agency`-shaped stdClass via BrandingResolver::asAgencyObject
     *  so emails.layout's existing $agency-typed accessors keep working. */
    public object $agency;

    /** Optional payment link surfaced in the email — caller can mint one
     *  via PaymentLinkController and pass the URL through. */
    public ?string $payUrl;

    /**
     * $tone controls subject line and intro paragraph. Valid values
     * mirror the BalanceRemindersTab buckets:
     *   - 'soft'        (default — first reminder, friendly)
     *   - 'firm'        (2-3 reminders sent, escalating)
     *   - 'final'       (final notice before collections)
     *   - 'collections' (collections-handoff notification)
     *   - 'neutral'     (legacy / initial statement — no urgency)
     */
    public function __construct(public PatientStatement $statement, ?string $payUrl = null, string $tone = 'neutral')
    {
        $this->payUrl = $payUrl;
        $this->tone = in_array($tone, ['soft', 'firm', 'final', 'collections', 'neutral'], true) ? $tone : 'neutral';
        $brand = BrandingResolver::forStatement($statement);
        $this->agency = BrandingResolver::asAgencyObject($brand);
    }

    public function envelope(): Envelope
    {
        $patient = $this->statement->patient_name ?: 'patient';
        $brand = $this->agency->name;
        $subject = match ($this->tone) {
            'soft'        => "Friendly reminder: balance due — {$brand}",
            'firm'        => "Past-due balance: please respond — {$brand}",
            'final'       => "FINAL NOTICE — balance overdue — {$brand}",
            'collections' => "Account being referred to collections — {$brand}",
            default       => "Statement of Account for {$patient} — {$brand}",
        };
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.patient-statement',
            with: [
                'agency'    => $this->agency,
                'statement' => $this->statement,
                'payUrl'    => $this->payUrl,
                'tone'      => $this->tone,
            ],
        );
    }
}
