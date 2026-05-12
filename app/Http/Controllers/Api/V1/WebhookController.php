<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Support\WebhookUrlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $webhooks = Webhook::orderByDesc('created_at')->get();

        // Expose only a fingerprint of the secret so the user can identify it without leaking the value.
        $webhooks->transform(function ($w) {
            $w->secret_preview = $w->secret ? substr($w->secret, 0, 8) . '...' : null;
            return $w;
        });

        return response()->json(['success' => true, 'data' => $webhooks]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'    => 'required|url|starts_with:https://|max:2048',
            'events' => 'required|array|min:1',
            'secret' => 'nullable|string|min:16|max:128',
        ]);

        WebhookUrlGuard::assertSafe($data['url']);

        $webhook = Webhook::create([
            'agency_id'  => $request->user()->effectiveAgencyId($request),
            'url'        => $data['url'],
            'secret'     => $data['secret'] ?? 'whsec_' . Str::random(32),
            'events'     => $data['events'],
            'is_active'  => true,
            'created_by' => $request->user()->id,
        ]);

        // Show the secret exactly once, on creation.
        $payload = $webhook->makeVisible('secret')->toArray();

        return response()->json([
            'success' => true,
            'data'    => $payload,
            'message' => 'Save the secret now — it will not be shown again.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $data = $request->validate([
            'url'       => 'sometimes|url|starts_with:https://|max:2048',
            'events'    => 'sometimes|array|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($data['url'])) {
            WebhookUrlGuard::assertSafe($data['url']);
        }

        $webhook->update($data);

        return response()->json(['success' => true, 'data' => $webhook->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);
        $webhook->delete();

        return response()->json(['success' => true, 'message' => 'Webhook deleted']);
    }

    public function test(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        // Re-check at delivery time — DNS may have changed since the URL was registered.
        WebhookUrlGuard::assertSafe($webhook->url);

        $payload = [
            'event'     => 'test',
            'data'      => [
                'message' => 'This is a test webhook from Credentik.',
                'agency'  => $request->user()->agency?->name,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $signature = hash_hmac('sha256', json_encode($payload), $webhook->secret);

        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'X-Credentik-Signature' => $signature,
                    'Content-Type'          => 'application/json',
                ])
                ->post($webhook->url, $payload);

            $webhook->update(['last_triggered_at' => now()]);

            if ($response->successful()) {
                $webhook->update(['failure_count' => 0]);
                return response()->json(['success' => true, 'message' => 'Test webhook sent, received ' . $response->status()]);
            }

            $webhook->increment('failure_count');
            return response()->json(['success' => false, 'message' => 'Webhook responded with ' . $response->status()], 422);
        } catch (\Exception $e) {
            $webhook->increment('failure_count');
            return response()->json(['success' => false, 'message' => 'Webhook failed: ' . $e->getMessage()], 422);
        }
    }

    /**
     * List recent delivery attempts for a webhook (audit / replay surface).
     */
    public function deliveries(Request $request, int $id): JsonResponse
    {
        // findOrFail enforces tenant scope via BelongsToAgency.
        Webhook::findOrFail($id);

        $deliveries = WebhookDelivery::where('webhook_id', $id)
            ->where('agency_id', $request->user()->effectiveAgencyId($request))
            ->orderByDesc('created_at')
            ->limit((int) min($request->input('limit', 100), 500))
            ->get();

        return response()->json(['success' => true, 'data' => $deliveries]);
    }
}
