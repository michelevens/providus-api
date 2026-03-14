<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = License::with('provider');
        if ($request->has('provider_id')) $query->where('provider_id', $request->provider_id);
        if ($request->has('state')) $query->where('state', $request->state);
        if ($request->has('status')) $query->where('status', $request->status);
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => 'required|exists:providers,id',
            'state' => 'required|string|size:2',
            'license_number' => 'nullable|string|max:50',
            'license_type' => 'nullable|string|max:20',
            'status' => 'required|in:active,pending,expired,inactive',
            'issue_date' => 'nullable|date', 'expiration_date' => 'nullable|date',
            'renewal_date' => 'nullable|date', 'compact_state' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        return response()->json(['success' => true, 'data' => License::create($data)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => License::with('provider')->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $license = License::findOrFail($id);
        $license->update($request->only([
            'provider_id', 'state', 'license_number', 'license_type', 'status',
            'issue_date', 'expiration_date', 'renewal_date', 'compact_state', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $license]);
    }

    public function destroy(int $id): JsonResponse
    {
        License::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
