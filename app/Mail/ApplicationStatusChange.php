<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChange extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public string $providerName,
        public string $payerName,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Application Status Update: {$this->providerName} — {$this->payerName}",
        );
    }

    public function build()
    {
        $statusColors = [
            'approved' => '#10b981', 'denied' => '#ef4444', 'submitted' => '#3b82f6',
            'in_progress' => '#f59e0b', 'on_hold' => '#8b5cf6', 'not_started' => '#6b7280',
        ];
        $color = $statusColors[$this->newStatus] ?? '#6b7280';
        $old = e($this->oldStatus);
        $new = e($this->newStatus);
        $provider = e($this->providerName);
        $payer = e($this->payerName);

        return $this->html("
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h2 style='color:#1a56db;'>Application Status Update</h2>
            <p>The credentialing application for <strong>{$provider}</strong> with <strong>{$payer}</strong> has been updated:</p>
            <div style='margin:16px 0;padding:16px;background:#f9fafb;border-radius:8px;'>
                <p style='margin:0;'>Status changed from <span style='font-weight:600;'>{$old}</span> to <span style='font-weight:600;color:{$color};'>{$new}</span></p>
            </div>
            <p style='color:#6b7280;font-size:12px;margin-top:24px;'>— Credentik</p>
        </div>");
    }
}
