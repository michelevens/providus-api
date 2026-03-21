<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrganizationContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationContactController extends Controller
{
    public function index(int $organizationId): JsonResponse
    {
        $contacts = OrganizationContact::where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $contacts]);
    }

    public function store(Request $request, int $organizationId): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        $data['organization_id'] = $organizationId;
        $data['agency_id'] = $request->user()->agency_id;

        $contact = OrganizationContact::create($data);

        return response()->json(['success' => true, 'data' => $contact], 201);
    }

    public function update(Request $request, int $organizationId, int $id): JsonResponse
    {
        $contact = OrganizationContact::where('organization_id', $organizationId)->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'title' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        $contact->update($data);

        return response()->json(['success' => true, 'data' => $contact]);
    }

    public function destroy(int $organizationId, int $id): JsonResponse
    {
        OrganizationContact::where('organization_id', $organizationId)->findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
