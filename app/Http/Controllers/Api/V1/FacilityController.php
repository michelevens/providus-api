<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FacilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Facility::where('agency_id', $request->user()->agency_id);
        if ($request->has('active_only')) $query->where('is_active', true);
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('npi', 'like', "%{$search}%")
                  ->orWhere('city', 'ilike', "%{$search}%");
            });
        }
        return response()->json(['success' => true, 'data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:200',
            'npi' => 'nullable|string|size:10',
            'facility_type' => 'nullable|string|max:50',
            'tax_id' => 'nullable|string|max:20',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zip' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:200',
            'website' => 'nullable|url|max:500',
            'contact_name' => 'nullable|string|max:200',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:200',
            'notes' => 'nullable|string',
        ]);

        $facility = Facility::create([
            'agency_id' => $request->user()->agency_id,
            ...$request->only([
                'name', 'npi', 'facility_type', 'tax_id', 'street', 'city',
                'state', 'zip', 'phone', 'fax', 'email', 'website',
                'contact_name', 'contact_phone', 'contact_email', 'notes',
            ]),
        ]);

        return response()->json(['success' => true, 'data' => $facility], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $facility = Facility::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        return response()->json(['success' => true, 'data' => $facility]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $facility = Facility::where('agency_id', $request->user()->agency_id)->findOrFail($id);
        $request->validate([
            'name' => 'sometimes|string|max:200',
            'npi' => 'sometimes|nullable|string|size:10',
            'facility_type' => 'sometimes|nullable|string|max:50',
            'tax_id' => 'sometimes|nullable|string|max:20',
            'street' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:2',
            'zip' => 'sometimes|nullable|string|max:10',
            'phone' => 'sometimes|nullable|string|max:20',
            'fax' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:200',
            'website' => 'sometimes|nullable|url|max:500',
            'contact_name' => 'sometimes|nullable|string|max:200',
            'contact_phone' => 'sometimes|nullable|string|max:20',
            'contact_email' => 'sometimes|nullable|email|max:200',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
        ]);
        $facility->update($request->only([
            'name', 'npi', 'facility_type', 'tax_id', 'street', 'city',
            'state', 'zip', 'phone', 'fax', 'email', 'website',
            'contact_name', 'contact_phone', 'contact_email', 'is_active', 'notes',
        ]));
        return response()->json(['success' => true, 'data' => $facility]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Facility::where('agency_id', $request->user()->agency_id)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // Auto-create facility from NPI lookup
    public function createFromNpi(Request $request): JsonResponse
    {
        $request->validate(['npi' => 'required|string|size:10']);

        try {
            $response = Http::timeout(10)
                ->get('https://npiregistry.cms.hhs.gov/api/', ['number' => $request->npi, 'version' => '2.1']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'External service unavailable'], 503);
        }

        if (!$response->successful()) {
            return response()->json(['success' => false, 'error' => 'NPI lookup failed'], 422);
        }

        $data = $response->json();
        $result = $data['results'][0] ?? null;
        if (!$result) {
            return response()->json(['success' => false, 'error' => 'NPI not found'], 404);
        }

        $basic = $result['basic'] ?? [];
        $address = collect($result['addresses'] ?? [])->firstWhere('address_purpose', 'LOCATION') ?? ($result['addresses'][0] ?? []);

        $name = $basic['organization_name'] ?? trim(($basic['first_name'] ?? '') . ' ' . ($basic['last_name'] ?? ''));

        $facility = Facility::create([
            'agency_id' => $request->user()->agency_id,
            'name' => $name,
            'npi' => $request->npi,
            'facility_type' => $basic['enumeration_type'] === 'NPI-2' ? 'organization' : 'individual',
            'street' => trim(($address['address_1'] ?? '') . ' ' . ($address['address_2'] ?? '')),
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'zip' => $address['postal_code'] ?? null,
            'phone' => $address['telephone_number'] ?? null,
            'fax' => $address['fax_number'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $facility, 'npi_data' => $basic], 201);
    }
}
