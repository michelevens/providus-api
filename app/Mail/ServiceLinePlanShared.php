<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\ServiceLineShareLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent to an external recipient with a tokenized download link
 * for a service-line business plan PDF. Uses the shared emails.layout
 * so the agency's brand color/logo/footer appear in the recipient's
 * inbox — the agency is the one "delivering" this plan; Credentik is
 * the platform footer.
 */
class ServiceLinePlanShared extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ServiceLineShareLink $link,
        public string $publicUrl,
        public Agency $agency,
    ) {}

    public function envelope(): Envelope
    {
        $agencyName = $this->agencyDisplayName();
        $fromAddress = config('mail.from.address', 'noreply@credentik.com');

        $envelope = new Envelope(
            from: new Address($fromAddress, $agencyName),
            subject: "{$this->link->service_line_name} — business plan from {$agencyName}",
        );

        if (!empty($this->agency->email)) {
            $envelope->replyTo = [new Address($this->agency->email, $agencyName)];
        }
        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.service-line-plan-shared',
            with: [
                'link'       => $this->link,
                'publicUrl'  => $this->publicUrl,
                'agency'     => $this->agency,
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
