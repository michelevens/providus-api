<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ShareLink;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShareLinkController extends Controller
{
    /**
     * Generate a share link for a provider (authenticated).
     */
    public function store(Request $request, int $providerId): JsonResponse
    {
        $provider = Provider::findOrFail($providerId);

        $token = Str::random(48);

        $link = ShareLink::create([
            'agency_id'   => $request->user()->agency_id,
            'provider_id' => $providerId,
            'token'       => $token,
            'created_by'  => $request->user()->email,
            'expires_at'  => now()->addDays(30),
            'is_active'   => true,
        ]);

        $frontendUrl = config('app.frontend_url', 'https://michelevans.github.io/providus-app');

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'url'   => $frontendUrl . '/#share/' . $token,
                'expires_at' => $link->expires_at,
            ],
        ], 201);
    }

    /**
     * View a shared provider profile (PUBLIC — no auth).
     */
    public function show(string $token): JsonResponse
    {
        $link = ShareLink::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (!$link || !$link->isValid()) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired share link'], 404);
        }

        // Increment view count
        $link->increment('view_count');

        // Load provider with related data (no sensitive fields)
        $provider = Provider::with([
            'licenses',
            'applications',
            'education',
            'boardCertifications',
        ])->find($link->provider_id);

        if (!$provider) {
            return response()->json(['success' => false, 'message' => 'Provider not found'], 404);
        }

        // Build safe summary (no SSN, DOB, addresses)
        $licenses = $provider->licenses ?? collect();
        $apps = $provider->applications ?? collect();
        $activeLicenses = $licenses->where('status', 'active')->count();
        $expiringLicenses = $licenses->filter(fn($l) => $l->status === 'active' && $l->expiration_date && now()->diffInDays($l->expiration_date, false) <= 90 && now()->diffInDays($l->expiration_date, false) > 0)->count();
        $expiredLicenses = $licenses->filter(fn($l) => $l->expiration_date && now()->gt($l->expiration_date))->count();

        $approvedApps = $apps->whereIn('status', ['approved', 'credentialed'])->count();
        $pendingApps = $apps->whereIn('status', ['submitted', 'in_review', 'pending_info', 'gathering_docs'])->count();
        $deniedApps = $apps->whereIn('status', ['denied', 'rejected'])->count();

        $totalChecks = 6;
        $passedChecks = 0;
        if ($activeLicenses > 0) $passedChecks++;
        if ($provider->npi) $passedChecks++;
        if ($expiredLicenses === 0) $passedChecks++;
        if ($provider->education && count($provider->education) > 0) $passedChecks++;
        if ($provider->boardCertifications && count($provider->boardCertifications) > 0) $passedChecks++;
        if ($approvedApps > 0) $passedChecks++;
        $completionPct = round(($passedChecks / $totalChecks) * 100);

        $agency = $link->agency ?? null;

        return response()->json([
            'success' => true,
            'data'    => [
                'provider' => [
                    'name'        => trim(($provider->first_name ?? '') . ' ' . ($provider->last_name ?? '')),
                    'credentials' => $provider->credentials ?? $provider->credential ?? '',
                    'npi'         => $provider->npi ?? '',
                    'specialty'   => $provider->specialty ?? '',
                ],
                'completionPct' => $completionPct,
                'licenses' => [
                    'active'   => $activeLicenses,
                    'expiring' => $expiringLicenses,
                    'expired'  => $expiredLicenses,
                ],
                'applications' => [
                    'approved' => $approvedApps,
                    'pending'  => $pendingApps,
                    'denied'   => $deniedApps,
                ],
                'agency' => $agency ? ['name' => $agency->name] : null,
                'generatedAt' => now()->toIso8601String(),
                'expiresAt'   => $link->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
