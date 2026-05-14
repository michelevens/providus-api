<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\BillingClient;
use App\Models\Claim;
use App\Models\User;
use App\Models\WriteOffRequest;
use App\Services\BrandingResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email to the org's contact requesting approval of a proposed
 * write-off on a claim that belongs to them.
 *
 * The email body contains the full context (amount, category, reason,
 * who proposed it) plus a one-click approve link and a reject link
 * — both signed by the portal_token on the WriteOffRequest row. The
 * org doesn't have a user account; the token IS the auth.
 *
 * Brand: pulls the billing_client's branding override (per-practice
 * white-labeling). When the org hasn't set one, falls back to the
 * agency's brand. Either way the email looks like it's from the
 * practice's billing department, not Credentik.
 */
class OrgWriteOffApprovalRequest extends Mailable
{
    use Queueable, SerializesModels;

    /** Resolved brand for this billing_client. */
    public object $agency;

    public string $approveUrl;
    public string $rejectUrl;
    public string $portalUrl;

    public function __construct(
        public WriteOffRequest $request,
        public Claim $claim,
        public ?User $requestedBy = null,
    ) {
        // Resolve branding from the billing_client (per-practice override
        // when set) or fall back to the agency's brand. Claims without a
        // billing_client get pure agency branding.
        $bc = $claim->billing_client_id ? BillingClient::find($claim->billing_client_id) : null;
        if ($bc) {
            $brand = BrandingResolver::forBillingClient($bc);
        } else {
            $agencyModel = Agency::find($claim->agency_id);
            $brand = $agencyModel
                ? BrandingResolver::forAgency($agencyModel)
                : ['name' => 'Credentik', 'primary_color' => '#0891b2', 'accent_color' => '#06b6d4'];
        }
        $this->agency = BrandingResolver::asAgencyObject($brand);

        $token = $request->portal_token;
        $base = config('app.frontend_url', 'https://app.credentik.com');
        // The portal lives under /v2/ because it's a V2 page; no auth
        // needed — the token IS the auth. Same pattern as the existing
        // public payment-link landing.
        $this->portalUrl  = sprintf('%s/v2/#/portal/write-off/%s', rtrim($base, '/'), $token);
        $this->approveUrl = $this->portalUrl . '?action=approve';
        $this->rejectUrl  = $this->portalUrl . '?action=reject';
    }

    public function envelope(): Envelope
    {
        $patient = $this->claim->patient_name ?: 'a patient';
        $amount = number_format((float) $this->request->amount, 2);
        return new Envelope(
            subject: sprintf(
                'Approve write-off: $%s on %s claim — %s',
                $amount,
                $patient,
                $this->agency->name,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.org-writeoff-approval',
            with: [
                'agency'         => $this->agency,
                'request'        => $this->request,
                'claim'          => $this->claim,
                'requestedBy'    => $this->requestedBy,
                'approveUrl'     => $this->approveUrl,
                'rejectUrl'      => $this->rejectUrl,
                'portalUrl'      => $this->portalUrl,
            ],
        );
    }
}
