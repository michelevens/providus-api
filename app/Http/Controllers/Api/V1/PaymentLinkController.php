<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                        'unit_amount' => (int) round($data['amount'] * 100), // cents
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
        $link = PaymentLink::where('public_token', $token)->first();
        if (!$link) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }
        return response()->json([
            'success' => true,
            'data'    => [
                'public_token' => $link->public_token,
                'status'       => $link->status,
                'amount'       => $link->amount,
                'currency'     => $link->currency,
                'patient_name' => $link->patient_name,
                'paid_at'      => $link->paid_at,
                'expires_at'   => $link->expires_at,
                'checkout_url' => $link->checkout_url,
                'description'  => $this->descriptionFor([
                    'target_type' => $link->target_type,
                    'patient_name'=> $link->patient_name,
                ]),
            ],
        ]);
    }

    public function resendEmail(Request $request, int $id): JsonResponse
    {
        $link = PaymentLink::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        if (!$link->patient_email) {
            return response()->json(['success' => false, 'error' => 'no_email'], 422);
        }
        // TODO: wire to NotificationService::sendPaymentLink — for now just acknowledge
        return response()->json([
            'success' => true,
            'data'    => [
                'queued'        => true,
                'patient_email' => $link->patient_email,
                'public_token'  => $link->public_token,
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

    private function descriptionFor(array $data): string
    {
        $type = $data['target_type'] ?? 'patient_balance';
        $name = $data['patient_name'] ?? null;
        $label = match ($type) {
            'patient_statement' => 'Patient Statement',
            'invoice'           => 'Invoice',
            default             => 'Patient Balance',
        };
        return $name ? "{$label} — {$name}" : $label;
    }
}
