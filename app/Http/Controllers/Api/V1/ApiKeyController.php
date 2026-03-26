<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keys = ApiKey::where('is_active', true)->orderByDesc('created_at')->get();

        // Mask the key for display
        $keys->transform(function ($key) {
            $key->masked_key = substr($key->key, 0, 8) . str_repeat('*', 24);
            return $key;
        });

        return response()->json(['success' => true, 'data' => $keys]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'permissions' => 'nullable|array',
        ]);

        $plainKey = 'ck_' . Str::random(32);
        $plainSecret = 'cs_' . Str::random(48);

        $apiKey = ApiKey::create([
            'agency_id'   => $request->user()->agency_id,
            'name'        => $data['name'],
            'key'         => $plainKey,
            'secret_hash' => bcrypt($plainSecret),
            'permissions' => $data['permissions'] ?? [],
            'is_active'   => true,
            'created_by'  => $request->user()->id,
        ]);

        // Return the plain secret ONE TIME
        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $apiKey->id,
                'name'        => $apiKey->name,
                'key'         => $plainKey,
                'secret'      => $plainSecret,
                'permissions' => $apiKey->permissions,
                'created_at'  => $apiKey->created_at,
            ],
            'message' => 'Save the secret now — it will not be shown again.',
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $key = ApiKey::findOrFail($id);
        $key->update(['is_active' => false]);
        $key->delete();

        return response()->json(['success' => true, 'message' => 'API key revoked']);
    }
}
