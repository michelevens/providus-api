<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\WebhookUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;
    public bool $afterCommit = true;

    /**
     * Auto-disable a webhook after this many consecutive failed deliveries.
     */
    private const FAILURE_DEACTIVATION_THRESHOLD = 20;

    public function __construct(
        public int $webhookId,
        public string $event,
        public array $data,
        public string $deliveryId,
    ) {}

    /**
     * Exponential backoff: 10s, 1m, 5m, 30m, 2h.
     */
    public function backoff(): array
    {
        return [10, 60, 300, 1800, 7200];
    }

    public function handle(): void
    {
        $webhook = Webhook::withoutGlobalScopes()->find($this->webhookId);
        $delivery = WebhookDelivery::where('delivery_id', $this->deliveryId)->first();

        if (!$webhook || !$webhook->is_active) {
            $this->finalizeDelivery($delivery, WebhookDelivery::STATUS_ABANDONED, null, null, 'Webhook missing or inactive at delivery time');
            return;
        }

        // Re-check at delivery time — DNS may have changed since registration.
        try {
            WebhookUrlGuard::assertSafe($webhook->url);
        } catch (\Throwable $e) {
            Log::warning("Webhook {$webhook->id} URL failed SSRF check at delivery: {$e->getMessage()}");
            $webhook->update(['is_active' => false]);
            $this->finalizeDelivery($delivery, WebhookDelivery::STATUS_ABANDONED, null, null, 'URL failed SSRF check at delivery');
            return;
        }

        $payload = [
            'event'       => $this->event,
            'delivery_id' => $this->deliveryId,
            'data'        => $this->data,
            'timestamp'   => now()->toIso8601String(),
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $webhook->secret);

        if ($delivery && !$delivery->first_attempt_at) {
            $delivery->update(['first_attempt_at' => now()]);
        }

        $response = Http::timeout(10)
            ->withOptions(['allow_redirects' => false])
            ->withHeaders([
                'X-Credentik-Event'       => $this->event,
                'X-Credentik-Delivery-Id' => $this->deliveryId,
                'X-Credentik-Signature'   => $signature,
                'Content-Type'            => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->post($webhook->url);

        if ($delivery) {
            $delivery->increment('attempt_count');
        }

        if ($response->successful()) {
            $webhook->update([
                'last_triggered_at' => now(),
                'failure_count'     => 0,
            ]);
            $this->finalizeDelivery($delivery, WebhookDelivery::STATUS_DELIVERED, $response->status(), $response->body(), null);
            return;
        }

        // Persist this attempt's response so the audit trail is complete even mid-retry.
        if ($delivery) {
            $delivery->update([
                'response_status' => $response->status(),
                'response_body'   => $this->truncate($response->body()),
                'error_message'   => "Webhook returned {$response->status()}",
            ]);
        }

        // Non-2xx — let Laravel retry per backoff(); failed() handles terminal state.
        throw new \RuntimeException("Webhook {$this->webhookId} responded {$response->status()}");
    }

    /**
     * Called by Laravel when the job runs out of retries.
     */
    public function failed(\Throwable $e): void
    {
        $webhook = Webhook::withoutGlobalScopes()->find($this->webhookId);
        $delivery = WebhookDelivery::where('delivery_id', $this->deliveryId)->first();

        if ($webhook) {
            $webhook->increment('failure_count');
            if ($webhook->failure_count >= self::FAILURE_DEACTIVATION_THRESHOLD) {
                $webhook->update(['is_active' => false]);
                Log::warning("Webhook {$webhook->id} auto-disabled after {$webhook->failure_count} consecutive failures.");
            }
        }

        $this->finalizeDelivery($delivery, WebhookDelivery::STATUS_FAILED, null, null, $e->getMessage());
    }

    private function finalizeDelivery(?WebhookDelivery $delivery, string $status, ?int $responseStatus, ?string $responseBody, ?string $error): void
    {
        if (!$delivery) return;
        $delivery->update([
            'status'          => $status,
            'response_status' => $responseStatus ?? $delivery->response_status,
            'response_body'   => $responseBody !== null ? $this->truncate($responseBody) : $delivery->response_body,
            'error_message'   => $error ?? $delivery->error_message,
            'completed_at'    => now(),
        ]);
    }

    private function truncate(?string $body): ?string
    {
        if ($body === null) return null;
        if (strlen($body) <= WebhookDelivery::MAX_RESPONSE_BODY_BYTES) return $body;
        return substr($body, 0, WebhookDelivery::MAX_RESPONSE_BODY_BYTES) . '… [truncated]';
    }
}
