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
            'facility_type' => 'nullable|string',
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
