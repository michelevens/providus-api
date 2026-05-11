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

    /** Resolved brand for THIS statement — practice override if set,
     *  agency fallback otherwise. Blade template still receives an
     *  `$agency`-shaped stdClass via BrandingResolver::asAgencyObject
     *  so emails.layout's existing $agency-typed accessors keep working. */
    public object $agency;

    /** Optional payment link surfaced in the email — caller can mint one
     *  via PaymentLinkController and pass the URL through. */
    public ?string $payUrl;

    public function __construct(public PatientStatement $statement, ?string $payUrl = null)
    {
        $this->payUrl = $payUrl;
        $brand = BrandingResolver::forStatement($statement);
        $this->agency = BrandingResolver::asAgencyObject($brand);
    }

    public function envelope(): Envelope
    {
        $patient = $this->statement->patient_name ?: 'patient';
        return new Envelope(subject: "Statement of Account for {$patient} — {$this->agency->name}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.patient-statement',
            with: [
                'agency' => $this->agency,
                'statement' => $this->statement,
                'payUrl' => $this->payUrl,
            ],
        );
    }
}
