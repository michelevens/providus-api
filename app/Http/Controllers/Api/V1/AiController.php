<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Provider;
use App\Models\ProviderDocument;
use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private AiService $aiService) {}

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

    /**
     * Extract structured data from an uploaded document via OCR/AI.
     */
    public function extractDocument(Request $request, int $documentId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $doc = ProviderDocument::where('agency_id', $agencyId)->findOrFail($documentId);

        $result = $this->aiService->extractDocument($doc);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'data' => $result['data'] ?? $result]);
    }

    /**
     * Generate an AI-powered email draft for a payer follow-up.
     */
    public function draftEmail(Request $request, int $applicationId): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:followup,escalation,document_request,initial_submission,provider_update',
            'context' => 'nullable|string|max:2000',
        ]);

        $agencyId = $this->resolveAgencyId($request);
        $app = Application::where('agency_id', $agencyId)->findOrFail($applicationId);

        $result = $this->aiService->draftEmail($app, $request->type, $request->context);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'data' => $result['data'] ?? $result]);
    }

    /**
     * Detect anomalies in a provider's profile.
     */
    public function detectAnomalies(Request $request, int $providerId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        Provider::where('agency_id', $agencyId)->findOrFail($providerId);

        $provider = Provider::with(['licenses'])->findOrFail($providerId);
        $result = $this->aiService->detectAnomalies($provider, $agencyId);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'data' => $result['data'] ?? $result]);
    }

    /**
     * Predict credentialing timeline for an application.
     */
    public function predictTimeline(Request $request, int $applicationId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        $app = Application::where('agency_id', $agencyId)->findOrFail($applicationId);

        $result = $this->aiService->predictTimeline($app, $agencyId);

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'data' => $result['data'] ?? $result]);
    }
}
