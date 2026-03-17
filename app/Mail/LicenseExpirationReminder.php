<?php

namespace App\Mail;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LicenseExpirationReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public License $license,
        public string $providerName,
        public int $daysUntilExpiry,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "License Expiring Soon: {$this->providerName}",
        );
    }

    public function build()
    {
        $license = $this->license;
        $expDate = $license->expiration_date?->format('M d, Y') ?? 'N/A';

        return $this->html("
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h2 style='color:#1a56db;'>License Expiration Reminder</h2>
            <p>The following license for <strong>{$this->providerName}</strong> is expiring in <strong>{$this->daysUntilExpiry} days</strong>:</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                <tr><td style='padding:8px;border:1px solid #e5e7eb;font-weight:600;'>License Number</td><td style='padding:8px;border:1px solid #e5e7eb;'>{$license->license_number}</td></tr>
                <tr><td style='padding:8px;border:1px solid #e5e7eb;font-weight:600;'>State</td><td style='padding:8px;border:1px solid #e5e7eb;'>{$license->state}</td></tr>
                <tr><td style='padding:8px;border:1px solid #e5e7eb;font-weight:600;'>Type</td><td style='padding:8px;border:1px solid #e5e7eb;'>{$license->license_type}</td></tr>
                <tr><td style='padding:8px;border:1px solid #e5e7eb;font-weight:600;'>Expiration Date</td><td style='padding:8px;border:1px solid #e5e7eb;'>{$expDate}</td></tr>
            </table>
            <p>Please take action to renew this license before it expires.</p>
            <p style='color:#6b7280;font-size:12px;margin-top:24px;'>— Credentik</p>
        </div>");
    }
}
