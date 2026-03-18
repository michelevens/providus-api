<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Customer;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Get current subscription status for the agency.
     */
    public function status(Request $request): JsonResponse
    {
        $agency = Agency::findOrFail($request->user()->agency_id);

        return response()->json([
            'success' => true,
            'data' => [
                'plan_tier' => $agency->plan_tier,
                'subscription_status' => $agency->subscription_status,
                'stripe_subscription_id' => $agency->stripe_subscription_id,
                'trial_ends_at' => $agency->trial_ends_at,
                'subscription_ends_at' => $agency->subscription_ends_at,
                'is_on_trial' => $agency->isOnTrial(),
                'is_subscribed' => $agency->isSubscribed(),
                'has_expired' => $agency->hasExpired(),
                'limits' => Agency::PLAN_LIMITS[$agency->plan_tier] ?? Agency::PLAN_LIMITS['starter'],
                'usage' => [
                    'providers' => $agency->providers()->count(),
                    'users' => $agency->users()->count(),
                    'applications' => $agency->applications()->count(),
                ],
            ],
        ]);
    }

    /**
     * Get available plans.
     */
    public function plans(): JsonResponse
    {
        $plans = [
            [
                'tier' => 'starter',
                'name' => 'Starter',
                'price' => 49,
                'interval' => 'month',
                'stripe_price_id' => config('services.stripe.plans.starter'),
                'features' => [
                    'Up to 5 providers',
                    '3 team members',
                    '50 applications/month',
                    'Basic reporting',
                    'Email support',
                ],
                'limits' => Agency::PLAN_LIMITS['starter'],
            ],
            [
                'tier' => 'professional',
                'name' => 'Professional',
                'price' => 149,
                'interval' => 'month',
                'stripe_price_id' => config('services.stripe.plans.professional'),
                'popular' => true,
                'features' => [
                    'Up to 25 providers',
                    '10 team members',
                    '500 applications/month',
                    'Advanced analytics & forecasting',
                    'AI-powered features',
                    'Priority support',
                    'Custom branding',
                ],
                'limits' => Agency::PLAN_LIMITS['professional'],
            ],
            [
                'tier' => 'enterprise',
                'name' => 'Enterprise',
                'price' => 349,
                'interval' => 'month',
                'stripe_price_id' => config('services.stripe.plans.enterprise'),
                'features' => [
                    'Unlimited providers',
                    'Unlimited team members',
                    'Unlimited applications',
                    'All AI features',
                    'Dedicated account manager',
                    'Custom integrations',
                    'SSO & advanced security',
                    'SLA guarantee',
                ],
                'limits' => Agency::PLAN_LIMITS['enterprise'],
            ],
        ];

        return response()->json(['success' => true, 'data' => $plans]);
    }

    /**
     * Create a Stripe Checkout session for new subscription or plan change.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_tier' => 'required|in:starter,professional,enterprise',
        ]);

        $agency = Agency::findOrFail($request->user()->agency_id);
        $priceId = config("services.stripe.plans.{$request->plan_tier}");

        if (!$priceId) {
            return response()->json(['success' => false, 'message' => 'Plan not configured.'], 422);
        }

        // Create or retrieve Stripe customer
        if (!$agency->stripe_customer_id) {
            $customer = Customer::create([
                'email' => $request->user()->email,
                'name' => $agency->name,
                'metadata' => ['agency_id' => $agency->id],
            ]);
            $agency->update(['stripe_customer_id' => $customer->id]);
        }

        // If already subscribed, create portal session for plan change
        if ($agency->stripe_subscription_id && $agency->subscription_status === 'active') {
            $portal = PortalSession::create([
                'customer' => $agency->stripe_customer_id,
                'return_url' => config('app.frontend_url', env('FRONTEND_URL')) . '/#billing',
            ]);

            return response()->json(['success' => true, 'data' => ['url' => $portal->url, 'type' => 'portal']]);
        }

        // Create checkout session for new subscription
        $sessionParams = [
            'customer' => $agency->stripe_customer_id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => config('app.frontend_url', env('FRONTEND_URL')) . '/#billing?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url', env('FRONTEND_URL')) . '/#billing?canceled=1',
            'metadata' => [
                'agency_id' => $agency->id,
                'plan_tier' => $request->plan_tier,
            ],
            'subscription_data' => [
                'metadata' => [
                    'agency_id' => $agency->id,
                    'plan_tier' => $request->plan_tier,
                ],
            ],
        ];

        // Add trial if agency hasn't had one yet
        if (!$agency->trial_ends_at && !$agency->stripe_subscription_id) {
            $sessionParams['subscription_data']['trial_period_days'] = 14;
        }

        $session = CheckoutSession::create($sessionParams);

        return response()->json(['success' => true, 'data' => ['url' => $session->url, 'type' => 'checkout']]);
    }

    /**
     * Create a Stripe Billing Portal session for managing subscription.
     */
    public function portal(Request $request): JsonResponse
    {
        $agency = Agency::findOrFail($request->user()->agency_id);

        if (!$agency->stripe_customer_id) {
            return response()->json(['success' => false, 'message' => 'No billing account found.'], 422);
        }

        $portal = PortalSession::create([
            'customer' => $agency->stripe_customer_id,
            'return_url' => config('app.frontend_url', env('FRONTEND_URL')) . '/#billing',
        ]);

        return response()->json(['success' => true, 'data' => ['url' => $portal->url]]);
    }

    /**
     * Cancel subscription (at period end).
     */
    public function cancel(Request $request): JsonResponse
    {
        $agency = Agency::findOrFail($request->user()->agency_id);

        if (!$agency->stripe_subscription_id) {
            return response()->json(['success' => false, 'message' => 'No active subscription.'], 422);
        }

        $subscription = \Stripe\Subscription::update($agency->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        $agency->update([
            'subscription_status' => 'canceling',
            'subscription_ends_at' => \Carbon\Carbon::createFromTimestamp($subscription->current_period_end),
        ]);

        return response()->json(['success' => true, 'message' => 'Subscription will cancel at period end.']);
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $agency = Agency::findOrFail($request->user()->agency_id);

        if (!$agency->stripe_subscription_id) {
            return response()->json(['success' => false, 'message' => 'No subscription to resume.'], 422);
        }

        \Stripe\Subscription::update($agency->stripe_subscription_id, [
            'cancel_at_period_end' => false,
        ]);

        $agency->update([
            'subscription_status' => 'active',
            'subscription_ends_at' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Subscription resumed.']);
    }
}
