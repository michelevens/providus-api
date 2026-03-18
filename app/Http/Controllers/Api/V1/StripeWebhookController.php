<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                $webhookSecret
            );
        } catch (\Exception $e) {
            Log::warning('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response('Invalid signature', 400);
        }

        $method = 'handle' . str_replace('.', '', ucwords($event->type, '.'));

        if (method_exists($this, $method)) {
            return $this->$method($event->data->object);
        }

        return response('OK', 200);
    }

    protected function handleCheckoutSessionCompleted($session)
    {
        $agencyId = $session->metadata->agency_id ?? null;
        $planTier = $session->metadata->plan_tier ?? 'starter';

        if (!$agencyId) {
            Log::warning('Stripe checkout: no agency_id in metadata');
            return response('OK', 200);
        }

        $agency = Agency::find($agencyId);
        if (!$agency) return response('OK', 200);

        $agency->update([
            'stripe_subscription_id' => $session->subscription,
            'plan_tier' => $planTier,
            'subscription_status' => 'active',
        ]);

        // If there's a trial, set trial_ends_at
        if ($session->subscription) {
            try {
                $sub = \Stripe\Subscription::retrieve($session->subscription);
                if ($sub->trial_end) {
                    $agency->update([
                        'subscription_status' => 'trialing',
                        'trial_ends_at' => Carbon::createFromTimestamp($sub->trial_end),
                    ]);
                }
                $agency->update(['stripe_price_id' => $sub->items->data[0]->price->id ?? null]);
            } catch (\Exception $e) {
                Log::warning('Stripe: could not retrieve subscription: ' . $e->getMessage());
            }
        }

        Log::info("Stripe: agency {$agencyId} subscribed to {$planTier}");
        return response('OK', 200);
    }

    protected function handleCustomerSubscriptionUpdated($subscription)
    {
        $agency = Agency::where('stripe_subscription_id', $subscription->id)->first();
        if (!$agency) {
            // Try by customer ID
            $agency = Agency::where('stripe_customer_id', $subscription->customer)->first();
        }
        if (!$agency) return response('OK', 200);

        $status = match ($subscription->status) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'unpaid',
            'incomplete' => 'incomplete',
            default => $subscription->status,
        };

        $updates = [
            'stripe_subscription_id' => $subscription->id,
            'subscription_status' => $subscription->cancel_at_period_end ? 'canceling' : $status,
            'stripe_price_id' => $subscription->items->data[0]->price->id ?? $agency->stripe_price_id,
        ];

        if ($subscription->trial_end) {
            $updates['trial_ends_at'] = Carbon::createFromTimestamp($subscription->trial_end);
        }
        if ($subscription->current_period_end) {
            $updates['subscription_ends_at'] = $subscription->cancel_at_period_end
                ? Carbon::createFromTimestamp($subscription->current_period_end)
                : null;
        }

        // Map price to plan tier
        $priceId = $updates['stripe_price_id'];
        foreach (['starter', 'professional', 'enterprise'] as $tier) {
            if (config("services.stripe.plans.{$tier}") === $priceId) {
                $updates['plan_tier'] = $tier;
                break;
            }
        }

        $agency->update($updates);
        Log::info("Stripe: subscription updated for agency {$agency->id}: {$status}");
        return response('OK', 200);
    }

    protected function handleCustomerSubscriptionDeleted($subscription)
    {
        $agency = Agency::where('stripe_subscription_id', $subscription->id)->first();
        if (!$agency) return response('OK', 200);

        $agency->update([
            'subscription_status' => 'canceled',
            'subscription_ends_at' => Carbon::createFromTimestamp($subscription->ended_at ?? now()->timestamp),
        ]);

        Log::info("Stripe: subscription canceled for agency {$agency->id}");
        return response('OK', 200);
    }

    protected function handleInvoicePaymentFailed($invoice)
    {
        $agency = Agency::where('stripe_customer_id', $invoice->customer)->first();
        if (!$agency) return response('OK', 200);

        $agency->update(['subscription_status' => 'past_due']);
        Log::warning("Stripe: payment failed for agency {$agency->id}");
        return response('OK', 200);
    }

    protected function handleInvoicePaymentSucceeded($invoice)
    {
        $agency = Agency::where('stripe_customer_id', $invoice->customer)->first();
        if (!$agency) return response('OK', 200);

        if ($agency->subscription_status === 'past_due') {
            $agency->update(['subscription_status' => 'active']);
        }

        Log::info("Stripe: payment succeeded for agency {$agency->id}");
        return response('OK', 200);
    }
}
