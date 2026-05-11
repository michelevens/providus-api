<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

/**
 * Patient-payment Stripe Checkout flow.
 *
 *  GET  /payments/config             public: returns whether Stripe is configured
 *                                    + the publishable key for the frontend
 *  POST /payments/checkout           auth:  create a Checkout Session, return URL
 *  GET  /payments/status/{token}     public: poll for paid/expired state
 *  POST /payments/{id}/resend        auth:  re-email the link (Resend integration)
 *  POST /payments/{id}/refund        auth:  refund via Stripe
 *
 * Spec for V2 side: v2/docs/STRIPE_INTEGRATION.md
 *
 * The "Send Pay Link" buttons in V2 (PatientDetailPage, InvoiceDetailPage,
 * StatementsTab) all hit /payments/checkout with target_type + target_id +
 * amount + patient_email. We mint a Stripe Checkout Session, save the
 * payment_links row, return the checkout URL. The patient gets it via the
 * UI's clipboard/email flow; V2 polls /payments/status/{token} for completion.
 */
class PaymentLinkController extends Controller
{
    public function config(): JsonResponse
    {
        $secret = config('services.stripe.secret');
        $publishable = config('services.stripe.publishable') ?? config('services.stripe.key');

        return response()->json([
            'success' => true,
            'data'    => [
                'configured' => !empty($secret),
                'mode'       => $secret && str_starts_with($secret, 'sk_live_') ? 'live'
                              : ($secret && str_starts_with($secret, 'sk_test_') ? 'test' : 'unset'),
                'publishable_key' => $publishable ?: null,
                'currency'   => 'usd',
            ],
        ]);
    }

    public function createCheckout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type'     => 'required|in:patient_balance,patient_statement,invoice',
            'target_id'       => 'nullable|integer',
            'amount'          => 'required|numeric|min:0.50',
            'patient_name'    => 'nullable|string|max:255',
            'patient_email'   => 'nullable|email|max:255',
            'description'     => 'nullable|string|max:500',
            'success_url'     => 'nullable|url',
            'cancel_url'      => 'nullable|url',
        ]);

        $secret = config('services.stripe.secret');
        if (!$secret) {
            return response()->json([
                'success' => false,
                'error'   => 'not_configured',
                'message' => 'Stripe is not configured on the backend. Set STRIPE_SECRET in Railway env.',
            ], 503);
        }
        Stripe::setApiKey($secret);

        $user = $request->user();
        // Default success/cancel URLs land on V2's public pay pages
        $base = config('app.frontend_url', 'https://app.credentik.com') . '/v2/#';
        $successUrl = $data['success_url'] ?? ($base . '/pay/success?token={CHECKOUT_SESSION_ID}');
        $cancelUrl  = $data['cancel_url']  ?? ($base . '/pay/cancel');

        // Create the payment_links row FIRST so we have the public_token to
        // embed in the Stripe metadata for round-trip lookups.
        $link = PaymentLink::create([
            'agency_id'      => $user->agency_id,
            'target_type'    => $data['target_type'],
            'target_id'      => $data['target_id'] ?? null,
            'patient_name'   => $data['patient_name'] ?? null,
            'patient_email'  => $data['patient_email'] ?? null,
            'amount'         => $data['amount'],
            'currency'       => 'usd',
            'status'         => 'pending',
            'expires_at'     => now()->addDays(30),
            'created_by'     => $user->id,
        ]);

        try {
            $session = StripeSession::create([
                'mode'                => 'payment',
                'payment_method_types'=> ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'product_data' => [
                            'name'        => $this->descriptionFor($data),
                        ],
                        // Float * 100 + round drops pennies on edge cases (e.g. 0.295*100=29.4999...).
                        // bcmul does string-based decimal math; we multiply at scale=2 so the
                        // result keeps any fractional cents (e.g. "18295.00"), then explicit
                        // round-half-up via bcadd('0.5') + intval-truncate matches accountant
                        // expectations. Required for money to avoid undercharging patients by
                        // 1¢ on ~half of statements.
                        'unit_amount' => (int) bcadd(bcmul((string) $data['amount'], '100', 2), '0.5', 0),
                    ],
                    'quantity' => 1,
                ]],
                'customer_email' => $data['patient_email'] ?? null,
                'success_url'    => $successUrl,
                'cancel_url'     => $cancelUrl,
                'metadata' => [
                    'agency_id'      => (string) $user->agency_id,
                    'payment_link_id'=> (string) $link->id,
                    'public_token'   => $link->public_token,
                    'target_type'    => $data['target_type'],
                    'target_id'      => (string) ($data['target_id'] ?? ''),
                ],
            ]);
        } catch (\Throwable $e) {
            $link->delete();
            return response()->json([
                'success' => false,
                'error'   => 'stripe_error',
                'message' => $e->getMessage(),
            ], 502);
        }

        $link->update([
            'stripe_session_id' => $session->id,
            'checkout_url'      => $session->url,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $link->id,
                'public_token'   => $link->public_token,
                'checkout_url'   => $session->url,
                'status'         => $link->status,
                'amount'         => $link->amount,
                'patient_email'  => $link->patient_email,
                'public_url'     => config('app.frontend_url', 'https://app.credentik.com') . '/v2/#/pay/' . $link->public_token,
            ],
        ], 201);
    }

    public function status(string $token): JsonResponse
    {
        // Public, unauthenticated endpoint — polled by patient-facing pay
        // pages. Response strips PHI (patient_name) so anyone who somehow
        // obtains a token learns only payment state + amount + a generic
        // description, not who the patient is. Expired links return 410 Gone
        // so callers can give up polling cleanly. The 40-char token + route
        // regex + throttle:30,1 makes enumeration impractical.
        $link = PaymentLink::where('public_token', $token)->first();
        if (!$link) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }
        if ($link->expires_at && $link->expires_at->isPast() && $link->status === 'pending') {
            return response()->json([
                'success' => false,
                'error'   => 'expired',
                'data'    => ['status' => 'expired', 'expires_at' => $link->expires_at],
            ], 410);
        }
        // Generic description — no patient name in the public response.
        $descLabel = match ($link->target_type) {
            'patient_statement' => 'Patient Statement',
            'invoice'           => 'Invoice',
            default             => 'Patient Balance',
        };
        return response()->json([
            'success' => true,
            'data'    => [
                'public_token' => $link->public_token,
                'status'       => $link->status,
                'amount'       => $link->amount,
                'currency'     => $link->currency,
                'paid_at'      => $link->paid_at,
                'expires_at'   => $link->expires_at,
                // checkout_url is fine — Stripe-issued, the patient already
                // had it via the link they clicked.
                'checkout_url' => $link->checkout_url,
                'description'  => $descLabel,
            ],
        ]);
    }

    public function resendEmail(Request $request, int $id): JsonResponse
    {
        $link = PaymentLink::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        if (!$link->patient_email) {
            return response()->json(['success' => false, 'error' => 'no_email'], 422);
        }
        if ($link->status === 'paid' || $link->status === 'refunded') {
            // Don't re-email patients for already-settled invoices — it's
            // confusing UX and we've seen the V2 button get double-clicked.
            return response()->json([
                'success' => false,
                'error'   => 'already_settled',
                'message' => 'This payment link is already ' . $link->status . '.',
            ], 422);
        }

        $apiKey = config('services.resend.key');
        if (!$apiKey) {
            // Service-unavailable rather than masking with success — V2 can
            // surface a real error instead of pretending the email went.
            Log::warning('Payment link resend skipped: RESEND_KEY not configured', ['link_id' => $link->id]);
            return response()->json([
                'success' => false,
                'error'   => 'email_not_configured',
                'message' => 'Email service not configured on this environment.',
            ], 503);
        }

        $agency = $request->user()->agency;
        $agencyName = $agency?->company_display_name ?: $agency?->name ?: 'Your provider';
        $publicUrl = config('app.frontend_url', 'https://app.credentik.com') . '/v2/#/pay/' . $link->public_token;
        $amount = number_format((float) $link->amount, 2);
        $fromAddress = config('mail.from.address', 'noreply@credentik.com');
        // Use a per-agency from-name when we can — Resend supports
        // "Name <addr>" syntax. e() guards against agency names containing
        // the < > " characters that would otherwise break the header.
        $fromHeader = sprintf('%s <%s>', e($agencyName), $fromAddress);

        $descLabel = match ($link->target_type) {
            'patient_statement' => 'patient statement',
            'invoice'           => 'invoice',
            default             => 'patient balance',
        };

        // Inline HTML matches the welcome-email shape used by register().
        // Patient-facing — keep it short, plain, and link-prominent.
        $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;padding:40px 20px;color:#111827;">'
              . '<div style="text-align:center;margin-bottom:32px;">'
              . '<div style="display:inline-block;background:#0891b2;color:#fff;font-size:20px;font-weight:700;padding:10px 18px;border-radius:10px;">' . e($agencyName) . '</div>'
              . '</div>'
              . '<h1 style="font-size:22px;font-weight:700;color:#111827;margin:0 0 12px;">Payment reminder</h1>'
              . '<p style="font-size:15px;color:#4b5563;line-height:1.6;margin:0 0 18px;">A payment of <strong style="color:#111827;">$' . e($amount) . '</strong> is awaiting your action on your ' . e($descLabel) . '.</p>'
              . '<div style="text-align:center;margin:32px 0;">'
              . '<a href="' . e($publicUrl) . '" style="display:inline-block;background:#0891b2;color:#fff;padding:14px 28px;border-radius:10px;font-weight:600;font-size:15px;text-decoration:none;">Pay $' . e($amount) . ' now</a>'
              . '</div>'
              . '<p style="font-size:13px;color:#6b7280;line-height:1.6;margin:0 0 8px;">Or paste this secure link into your browser:</p>'
              . '<p style="font-size:12px;color:#0891b2;word-break:break-all;margin:0 0 24px;"><a href="' . e($publicUrl) . '" style="color:#0891b2;">' . e($publicUrl) . '</a></p>'
              . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">'
              . '<p style="font-size:12px;color:#9ca3af;text-align:center;line-height:1.6;">Payments are processed securely by Stripe. If you have questions, reply to this email.</p>'
              . '</div>';

        $resendId = null;
        $status = 'queued';
        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.resend.com/emails', [
                    'from'     => $fromHeader,
                    'to'       => [$link->patient_email],
                    'subject'  => 'Payment reminder from ' . $agencyName . ' — $' . $amount,
                    'html'     => $html,
                    'reply_to' => $agency?->email ?: null,
                ]);

            if ($response->successful()) {
                $resendId = $response->json('id');
                $status = 'sent';
            } else {
                $status = 'failed';
                Log::warning('Payment link resend failed at Resend', [
                    'link_id' => $link->id,
                    'status'  => $response->status(),
                    // Body may contain Resend's structured error — useful
                    // for triage. No patient PII in the body itself.
                    'body'    => substr((string) $response->body(), 0, 500),
                ]);
                return response()->json([
                    'success' => false,
                    'error'   => 'email_send_failed',
                    'message' => 'Email provider rejected the message. Please verify the patient email and try again.',
                ], 502);
            }
        } catch (\Throwable $e) {
            Log::error('Payment link resend exception', [
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error'   => 'email_send_exception',
                'message' => 'Could not reach the email provider. Please try again in a moment.',
            ], 502);
        }

        // Best-effort log — failure here shouldn't block the success response
        // since the email already went out.
        try {
            \App\Models\NotificationLogEntry::create([
                'agency_id'       => $link->agency_id,
                'type'            => 'payment_link_resend',
                'recipient_email' => $link->patient_email,
                'recipient_name'  => $link->patient_name,
                'subject'         => 'Payment reminder from ' . $agencyName . ' — $' . $amount,
                'body'            => $html,
                'status'          => $status === 'sent' ? 'delivered' : $status,
                'resend_id'       => $resendId,
                'metadata'        => [
                    'payment_link_id' => $link->id,
                    'public_token'    => $link->public_token,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Payment link resend log entry failed (non-fatal)', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'sent'          => true,
                'patient_email' => $link->patient_email,
                'public_token'  => $link->public_token,
                'resend_id'     => $resendId,
            ],
        ]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $link = PaymentLink::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        if ($link->status !== 'paid') {
            return response()->json(['success' => false, 'error' => 'not_paid', 'message' => 'Can only refund paid links'], 422);
        }
        if (!$link->stripe_payment_intent_id) {
            return response()->json(['success' => false, 'error' => 'no_payment_intent'], 422);
        }

        $secret = config('services.stripe.secret');
        if (!$secret) {
            return response()->json(['success' => false, 'error' => 'not_configured'], 503);
        }
        Stripe::setApiKey($secret);

        try {
            \Stripe\Refund::create(['payment_intent' => $link->stripe_payment_intent_id]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'stripe_error', 'message' => $e->getMessage()], 502);
        }

        $link->update(['status' => 'refunded']);
        return response()->json(['success' => true, 'data' => $link]);
    }

    /**
     * Stripe product description shown on the Checkout page AND included in
     * Stripe dashboard exports, financial reports, and chargeback notices.
     * We deliberately exclude patient_name here — including it would leak
     * PHI into every Stripe report. The agency can still see who the payment
     * was for via their PaymentLink row (which carries patient_name as a
     * server-side field) plus the `target_id` reference.
     *
     * The internal description in the dashboard (`metadata`) can reference
     * the link id for back-pointer purposes; the customer-facing line item
     * stays generic.
     */
    private function descriptionFor(array $data): string
    {
        $type = $data['target_type'] ?? 'patient_balance';
        return match ($type) {
            'patient_statement' => 'Patient Statement',
            'invoice'           => 'Invoice',
            default             => 'Patient Balance',
        };
    }
}
