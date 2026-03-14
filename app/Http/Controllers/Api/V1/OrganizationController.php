<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Organization::with('providers')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'npi' => 'nullable|string|size:10',
            'tax_id' => 'nullable|string|max:20',
            'address_street' => 'nullable|string', 'address_city' => 'nullable|string',
            'address_state' => 'nullable|string|size:2', 'address_zip' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20', 'email' => 'nullable|email',
            'taxonomy' => 'nullable|string|max:20',
        ]);

        $org = Organization::create($data);
        return response()->json(['success' => true, 'data' => $org], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Organization::with('providers')->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $org->update($request->only([
            'name', 'npi', 'tax_id', 'address_street', 'address_city',
            'address_state', 'address_zip', 'phone', 'email', 'taxonomy',
        ]));
        return response()->json(['success' => true, 'data' => $org]);
    }

    public function destroy(int $id): JsonResponse
    {
        Organization::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
