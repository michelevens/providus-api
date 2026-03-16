<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeaRegistration;
use App\Models\License;
use App\Models\LicenseVerification;
use App\Services\LicenseMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    private function resolveAgencyId(Request $request): int
    {
        $user = $request->user();
        $agencyId = $user->agency_id;
        if (!$agencyId && $user->role === 'superadmin' && $request->header('X-Agency-Id')) {
            $agencyId = (int) $request->header('X-Agency-Id');
        }
        abort_unless($agencyId, 400, 'No agency context.');
        return $agencyId;
    }

    // ── Standard CRUD ──

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
        $data = $request->only([
            'provider_id', 'state', 'license_number', 'license_type', 'status',
            'issue_date', 'expiration_date', 'renewal_date', 'compact_state', 'notes',
        ]);
        foreach (['issue_date', 'expiration_date', 'renewal_date'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }
        $license->update($data);
        return response()->json(['success' => true, 'data' => $license]);
    }

    public function destroy(int $id): JsonResponse
    {
        License::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    // ── License Monitoring ──

    public function monitoringSummary(Request $request, LicenseMonitoringService $service): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        return response()->json(['success' => true, 'data' => $service->getMonitoringSummary($agencyId)]);
    }

    public function expiring(Request $request, LicenseMonitoringService $service): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        return response()->json(['success' => true, 'data' => $service->getExpiringItems($agencyId)]);
    }

    public function verify(Request $request, int $id, LicenseMonitoringService $service): JsonResponse
    {
        $license = License::findOrFail($id);
        $verification = $service->verifyLicense($license, $request->user()->id);
        return response()->json(['success' => true, 'data' => $verification]);
    }

    public function verifyAll(Request $request, LicenseMonitoringService $service): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $results = $service->verifyAllForAgency($agencyId, $request->user()->id);
        return response()->json(['success' => true, 'data' => $results]);
    }

    public function verifications(Request $request): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $query = LicenseVerification::where('agency_id', $agencyId)
            ->with(['license', 'provider:id,first_name,last_name,credentials,npi'])
            ->orderByDesc('verified_at');

        if ($request->has('license_id')) $query->where('license_id', $request->license_id);
        if ($request->has('status')) $query->where('status', $request->status);

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    // ── DEA Registration CRUD ──

    public function deaIndex(Request $request): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $query = DeaRegistration::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->with('provider:id,first_name,last_name,credentials,npi');

        if ($request->has('provider_id')) $query->where('provider_id', $request->provider_id);
        if ($request->has('status')) $query->where('status', $request->status);

        return response()->json(['success' => true, 'data' => $query->orderBy('expiration_date')->get()]);
    }

    public function deaStore(Request $request): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $data = $request->validate([
            'provider_id' => 'required|exists:providers,id',
            'dea_number' => 'required|string|max:20',
            'schedules' => 'nullable|array',
            'state' => 'nullable|string|size:2',
            'business_activity' => 'nullable|string|max:100',
            'drug_category' => 'nullable|string|max:50',
            'status' => 'required|in:active,expired,revoked,surrendered',
            'expiration_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['agency_id'] = $agencyId;

        $dea = DeaRegistration::create($data);
        return response()->json(['success' => true, 'data' => $dea], 201);
    }

    public function deaUpdate(Request $request, int $id): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $dea = DeaRegistration::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->findOrFail($id);

        $data = $request->only([
            'dea_number', 'schedules', 'state', 'business_activity',
            'drug_category', 'status', 'expiration_date', 'notes',
        ]);
        if (array_key_exists('expiration_date', $data) && $data['expiration_date'] === '') {
            $data['expiration_date'] = null;
        }
        $dea->update($data);
        return response()->json(['success' => true, 'data' => $dea->fresh()]);
    }

    public function deaDestroy(Request $request, int $id): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        DeaRegistration::withoutGlobalScopes()
            ->where('agency_id', $agencyId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
