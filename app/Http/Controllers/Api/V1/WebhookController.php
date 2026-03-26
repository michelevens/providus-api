<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => Webhook::orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'    => 'required|url|starts_with:https://',
            'events' => 'required|array|min:1',
            'secret' => 'nullable|string',
        ]);

        $webhook = Webhook::create([
            'agency_id'  => $request->user()->agency_id,
            'url'        => $data['url'],
            'secret'     => $data['secret'] ?? 'whsec_' . Str::random(32),
            'events'     => $data['events'],
            'is_active'  => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $webhook], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $data = $request->validate([
            'url'       => 'sometimes|url|starts_with:https://',
            'events'    => 'sometimes|array|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

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
}
