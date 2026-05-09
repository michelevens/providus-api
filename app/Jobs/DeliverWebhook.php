<?php

namespace App\Jobs;

use App\Models\Webhook;
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
        if (!$webhook || !$webhook->is_active) {
            return;
        }

        // Re-check at delivery time — DNS may have changed since registration.
        try {
            WebhookUrlGuard::assertSafe($webhook->url);
        } catch (\Throwable $e) {
            Log::warning("Webhook {$webhook->id} URL failed SSRF check at delivery: {$e->getMessage()}");
            $webhook->update(['is_active' => false]);
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

        if ($response->successful()) {
            $webhook->update([
                'last_triggered_at' => now(),
                'failure_count'     => 0,
            ]);
            return;
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
        if (!$webhook) {
            return;
        }

        $webhook->increment('failure_count');

        if ($webhook->failure_count >= self::FAILURE_DEACTIVATION_THRESHOLD) {
            $webhook->update(['is_active' => false]);
            Log::warning("Webhook {$webhook->id} auto-disabled after {$webhook->failure_count} consecutive failures.");
        }
    }
}
