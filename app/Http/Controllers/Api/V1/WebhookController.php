<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $this->assertSafeUrl($data['url']);

        $webhook = Webhook::create([
            'agency_id'  => $request->user()->agency_id,
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
            $this->assertSafeUrl($data['url']);
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
        $this->assertSafeUrl($webhook->url);

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
     * Reject webhook URLs that resolve to private/loopback/link-local IPs (SSRF guard).
     * Validates host AND resolved IP — defends against DNS rebinding at registration time.
     */
    private function assertSafeUrl(string $url): void
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            throw ValidationException::withMessages(['url' => ['Invalid URL.']]);
        }

        $host = strtolower($parsed['host']);

        // Block obvious internal hostnames before DNS lookup.
        $blockedHostnames = ['localhost', 'metadata.google.internal', 'metadata.goog'];
        if (in_array($host, $blockedHostnames, true)) {
            throw ValidationException::withMessages(['url' => ['URL host is not allowed.']]);
        }

        // Resolve to IPs (handles literal IPs and DNS names).
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            foreach ($records ?: [] as $r) {
                if (!empty($r['ip']))    $ips[] = $r['ip'];
                if (!empty($r['ipv6']))  $ips[] = $r['ipv6'];
            }
        }

        if (empty($ips)) {
            throw ValidationException::withMessages(['url' => ['Could not resolve URL host.']]);
        }

        foreach ($ips as $ip) {
            // Reject private (RFC1918), loopback, link-local, reserved.
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw ValidationException::withMessages(['url' => ['URL must not point to a private or loopback address.']]);
            }
        }
    }
}
