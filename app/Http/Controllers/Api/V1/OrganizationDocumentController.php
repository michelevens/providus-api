<?php

namespace App\Http\Controllers\Api\V1;

// OrganizationDocumentController — file storage for organization-scoped
// artifacts (W-9, voided checks, certificates of insurance, signed
// contracts, HIPAA BAAs, articles of incorporation, business licenses,
// CAQH attestations, doc-request responses).
//
// Mirrors PatientDocumentController + DocumentController shape, keyed
// by organization_id (real FK, not a fuzzy key like patient_key).
// Same R2 backend, same status vocabulary, same 503-on-storage-fail
// hardening from the Week 1 push.

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrganizationDocumentController extends Controller
{
    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('filesystems.default') === 'local' ? 'local' : 's3');
    }

    private function resolveAgencyId(Request $request): int
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');
        return $agencyId;
    }

    /**
     * GET /organizations/{orgId}/documents
     */
    public function index(Request $request, int $orgId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);

        // Sanity: org must belong to this agency. findOrFail honors
        // TenantScope so cross-tenant probing returns 404.
        Organization::where('agency_id', $agencyId)->findOrFail($orgId);

        $docs = OrganizationDocument::where('agency_id', $agencyId)
            ->where('organization_id', $orgId)
            ->orderBy('document_type')
            ->get();

        $expiringSoon = $docs->filter(fn ($d) => $d->expiration_date && $d->expiration_date->between(now(), now()->addDays(90)))->count();
        $expired      = $docs->filter(fn ($d) => $d->expiration_date && $d->expiration_date->isPast())->count();

        return response()->json([
            'success' => true,
            'data'    => $docs,
            'summary' => [
                'total'         => $docs->count(),
                'with_file'     => $docs->whereNotNull('file_path')->count(),
                'expired'       => $expired,
                'expiring_soon' => $expiringSoon,
            ],
        ]);
    }

    /**
     * POST /organizations/{orgId}/documents/upload
     * multipart/form-data with `file`.
     *
     * If document_request_id is provided AND matches an open request
     * on this org, the upload is linked and the request's lifecycle
     * status is recomputed (pending → partial → fulfilled).
     */
    public function upload(Request $request, int $orgId): JsonResponse
    {
        $request->validate([
            'file'                => 'required|file|max:20480',
            'document_type'       => 'required|string|max:100',
            'document_name'       => 'required|string|max:200',
            'expiration_date'     => 'nullable|date',
            'notes'               => 'nullable|string|max:2000',
            'document_request_id' => 'nullable|integer',
        ]);

        $agencyId = $this->resolveAgencyId($request);
        Organization::where('agency_id', $agencyId)->findOrFail($orgId);

        $file = $request->file('file');
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = "organization-documents/{$agencyId}/{$orgId}/{$filename}";

        try {
            $this->disk()->put($path, file_get_contents($file->getRealPath()));
        } catch (\Throwable $e) {
            Log::error('organization-document R2 put failed', ['agency_id' => $agencyId, 'org_id' => $orgId, 'err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Storage unavailable — please retry.'], 503);
        }

        // Validate document_request_id belongs to this org and agency
        // before linking — refuse cross-tenant or cross-org IDs.
        $requestId = $request->input('document_request_id');
        $linkedRequest = null;
        if ($requestId) {
            $linkedRequest = DocumentRequest::where('agency_id', $agencyId)
                ->where('id', $requestId)
                ->where('organization_id', $orgId)
                ->first();
            if (!$linkedRequest) {
                // Don't fail the upload — just skip the linkback. The
                // file still lands and the operator can wire it later.
                Log::warning('org doc upload: document_request_id rejected', [
                    'request_id' => $requestId, 'org_id' => $orgId,
                ]);
            }
        }

        $doc = OrganizationDocument::create([
            'agency_id'         => $agencyId,
            'organization_id'   => $orgId,
            'document_type'     => $request->document_type,
            'document_name'     => $request->document_name,
            'file_path'         => $path,
            'file_disk'         => config('filesystems.default') === 'local' ? 'local' : 's3',
            'mime_type'         => $file->getClientMimeType(),
            'file_size'         => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by'       => $request->user()->id,
            'status'            => 'received',
            'received_date'     => now()->toDateString(),
            'expiration_date'   => $request->expiration_date,
            'notes'             => $request->notes,
            'document_request_id' => $linkedRequest?->id,
        ]);

        // If linked to a request, recompute the request's lifecycle.
        if ($linkedRequest) {
            $linkedRequest->recomputeStatus();
        }

        return response()->json(['success' => true, 'data' => $doc], 201);
    }

    /**
     * GET /organizations/{orgId}/documents/{id}/download
     * For R2: returns a 15-minute presigned URL. For local: streams.
     */
    public function download(Request $request, int $orgId, int $id)
    {
        $agencyId = $this->resolveAgencyId($request);

        $doc = OrganizationDocument::where('agency_id', $agencyId)
            ->where('organization_id', $orgId)
            ->findOrFail($id);

        if (!$doc->file_path) {
            return response()->json(['success' => false, 'message' => 'No file attached'], 404);
        }
        $disk = Storage::disk($doc->file_disk ?? 's3');
        if (!$disk->exists($doc->file_path)) {
            return response()->json(['success' => false, 'message' => 'File not found in storage'], 404);
        }

        if ($doc->file_disk !== 'local') {
            $url = $disk->temporaryUrl($doc->file_path, now()->addMinutes(15));
            return response()->json(['success' => true, 'data' => ['url' => $url, 'filename' => $doc->original_filename]]);
        }
        return $disk->download($doc->file_path, $doc->original_filename);
    }

    /**
     * PUT /organizations/{orgId}/documents/{id}
     */
    public function update(Request $request, int $orgId, int $id): JsonResponse
    {
        $request->validate([
            'document_type'   => 'sometimes|string|max:100',
            'document_name'   => 'sometimes|string|max:200',
            'status'          => 'sometimes|string|max:20',
            'expiration_date' => 'sometimes|nullable|date',
            'notes'           => 'sometimes|nullable|string|max:2000',
        ]);
        $agencyId = $this->resolveAgencyId($request);

        $doc = OrganizationDocument::where('agency_id', $agencyId)
            ->where('organization_id', $orgId)
            ->findOrFail($id);
        $doc->update($request->only(['document_type', 'document_name', 'status', 'expiration_date', 'notes']));
        return response()->json(['success' => true, 'data' => $doc->fresh()]);
    }

    /**
     * DELETE /organizations/{orgId}/documents/{id}
     */
    public function destroy(Request $request, int $orgId, int $id): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);

        $doc = OrganizationDocument::where('agency_id', $agencyId)
            ->where('organization_id', $orgId)
            ->findOrFail($id);

        if ($doc->file_path) {
            try {
                Storage::disk($doc->file_disk ?? 's3')->delete($doc->file_path);
            } catch (\Throwable $e) {
                Log::warning('org doc R2 delete failed', ['id' => $doc->id, 'err' => $e->getMessage()]);
            }
        }
        $requestId = $doc->document_request_id;
        $doc->delete();

        // If the deleted upload was linked to a request, recompute its
        // status (fulfilled → partial → pending).
        if ($requestId) {
            $req = DocumentRequest::where('agency_id', $agencyId)->find($requestId);
            $req?->recomputeStatus();
        }

        return response()->json(['success' => true]);
    }
}
