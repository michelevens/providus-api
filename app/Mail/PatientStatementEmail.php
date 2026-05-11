<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\PatientStatement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatientStatementEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Agency $agency;

    /** Optional payment link surfaced in the email — caller can mint one
     *  via PaymentLinkController and pass the URL through. */
    public ?string $payUrl;

    public function __construct(public PatientStatement $statement, ?string $payUrl = null)
    {
        $this->payUrl = $payUrl;
        // Resolve sending tenant for the branded layout. Falls back to a
        // bare Agency model when something has detached the statement from
        // its tenant — emails.layout already degrades gracefully.
        $this->agency = Agency::find($statement->agency_id) ?? new Agency(['name' => 'Credentik']);
    }

    public function envelope(): Envelope
    {
        $tenant = $this->agency->company_display_name ?: $this->agency->name;
        $patient = $this->statement->patient_name ?: 'patient';
        return new Envelope(subject: "Statement of Account for {$patient} — {$tenant}");
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
