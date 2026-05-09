<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Webhook;
use Illuminate\Support\Str;

/**
 * Resolves which webhooks subscribe to a given event for an agency
 * and queues a DeliverWebhook job for each. Use the EVENTS constants
 * as the canonical event names — anything else is acceptable but
 * won't show up in API docs.
 */
class WebhookDispatcher
{
    public const APPLICATION_CREATED        = 'application.created';
    public const APPLICATION_STATUS_CHANGED = 'application.status_changed';
    public const APPLICATION_APPROVED       = 'application.approved';
    public const APPLICATION_DENIED         = 'application.denied';
    public const APPLICATION_DELETED        = 'application.deleted';

    public const CLAIM_SUBMITTED = 'claim.submitted';
    public const CLAIM_PAID      = 'claim.paid';
    public const CLAIM_DENIED    = 'claim.denied';

    public const PAYMENT_POSTED = 'payment.posted';

    public const PROVIDER_ONBOARDED = 'provider.onboarded';
    public const LICENSE_EXPIRING   = 'license.expiring';
    public const LICENSE_EXPIRED    = 'license.expired';

    public const EVENTS = [
        self::APPLICATION_CREATED,
        self::APPLICATION_STATUS_CHANGED,
        self::APPLICATION_APPROVED,
        self::APPLICATION_DENIED,
        self::APPLICATION_DELETED,
        self::CLAIM_SUBMITTED,
        self::CLAIM_PAID,
        self::CLAIM_DENIED,
        self::PAYMENT_POSTED,
        self::PROVIDER_ONBOARDED,
        self::LICENSE_EXPIRING,
        self::LICENSE_EXPIRED,
    ];

    /**
     * Queue a delivery for every webhook in the agency that subscribes to $event.
     * Subscriptions: webhook.events array contains $event, or contains '*'.
     */
    public static function dispatch(int $agencyId, string $event, array $data): void
    {
        $webhooks = Webhook::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn(Webhook $w) => self::subscribesTo($w, $event));

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch(
                $webhook->id,
                $event,
                $data,
                (string) Str::uuid(),
            );
        }
    }

    private static function subscribesTo(Webhook $webhook, string $event): bool
    {
        $events = $webhook->events ?? [];
        if (!is_array($events)) {
            return false;
        }
        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
