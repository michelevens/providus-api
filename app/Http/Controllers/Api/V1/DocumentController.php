<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\ProviderDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('filesystems.default') === 'local' ? 'local' : 's3');
    }

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
     * Upload a file for a provider document.
     * Accepts multipart/form-data with a 'file' field.
     */
    public function upload(Request $request, int $providerId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20MB max
            'document_type' => 'required|string|max:100',
            'document_name' => 'required|string|max:200',
            'expiration_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        $agencyId = $this->resolveAgencyId($request);

        // Verify provider belongs to agency
        Provider::where('agency_id', $agencyId)->findOrFail($providerId);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = "documents/{$agencyId}/{$providerId}/{$filename}";

        // Store file
        $this->disk()->put($path, file_get_contents($file->getRealPath()));

        $doc = ProviderDocument::create([
            'agency_id' => $agencyId,
            'provider_id' => $providerId,
            'document_type' => $request->document_type,
            'document_name' => $request->document_name,
            'file_path' => $path,
            'file_disk' => config('filesystems.default') === 'local' ? 'local' : 's3',
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by' => $user->id,
            'status' => 'received',
            'received_date' => now()->toDateString(),
            'expiration_date' => $request->expiration_date,
            'notes' => $request->notes,
        ]);

        return response()->json(['success' => true, 'data' => $doc], 201);
    }

    /**
     * Replace the file on an existing document record.
     */
    public function replace(Request $request, int $providerId, int $documentId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $user = $request->user();
        $agencyId = $this->resolveAgencyId($request);

        $doc = ProviderDocument::where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->findOrFail($documentId);

        // Delete old file if exists
        if ($doc->file_path && $this->disk()->exists($doc->file_path)) {
            $this->disk()->delete($doc->file_path);
        }

        $file = $request->file('file');
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = "documents/{$agencyId}/{$providerId}/{$filename}";

        $this->disk()->put($path, file_get_contents($file->getRealPath()));

        $doc->update([
            'file_path' => $path,
            'file_disk' => config('filesystems.default') === 'local' ? 'local' : 's3',
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by' => $user->id,
            'status' => 'received',
            'received_date' => now()->toDateString(),
        ]);

        return response()->json(['success' => true, 'data' => $doc->fresh()]);
    }

    /**
     * Get a temporary download URL for a document.
     * For S3/R2 returns a presigned URL; for local returns a streamed response.
     */
    public function download(Request $request, int $providerId, int $documentId): JsonResponse|StreamedResponse
    {
        $agencyId = $this->resolveAgencyId($request);

        $doc = ProviderDocument::where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->findOrFail($documentId);

        if (!$doc->file_path) {
            return response()->json(['success' => false, 'message' => 'No file attached'], 404);
        }

        $disk = Storage::disk($doc->file_disk ?? 's3');

        if (!$disk->exists($doc->file_path)) {
            return response()->json(['success' => false, 'message' => 'File not found in storage'], 404);
        }

        // For S3/R2, return a temporary signed URL
        if ($doc->file_disk !== 'local') {
            $url = $disk->temporaryUrl($doc->file_path, now()->addMinutes(15));
            return response()->json(['success' => true, 'data' => ['url' => $url, 'filename' => $doc->original_filename]]);
        }

        // For local disk, stream the file
        return $disk->download($doc->file_path, $doc->original_filename);
    }

    /**
     * Delete a document and its file from storage.
     */
    public function destroy(Request $request, int $providerId, int $documentId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);

        $doc = ProviderDocument::where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->findOrFail($documentId);

        // Delete file from storage
        if ($doc->file_path) {
            $disk = Storage::disk($doc->file_disk ?? 's3');
            if ($disk->exists($doc->file_path)) {
                $disk->delete($doc->file_path);
            }
        }

        $doc->delete();

        return response()->json(['success' => true]);
    }

    /**
     * List all documents for a provider with summary stats.
     */
    public function index(Request $request, int $providerId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        Provider::where('agency_id', $agencyId)->findOrFail($providerId);

        $docs = ProviderDocument::where('agency_id', $agencyId)
            ->where('provider_id', $providerId)
            ->orderBy('document_type')
            ->get();

        $expiringSoon = $docs->filter(function ($d) {
            return $d->expiration_date && $d->expiration_date->between(now(), now()->addDays(90));
        })->count();

        $expired = $docs->filter(function ($d) {
            return $d->expiration_date && $d->expiration_date->isPast();
        })->count();

        $missing = $docs->where('status', 'missing')->count();

        return response()->json([
            'success' => true,
            'data' => $docs,
            'summary' => [
                'total' => $docs->count(),
                'with_file' => $docs->whereNotNull('file_path')->count(),
                'missing' => $missing,
                'expired' => $expired,
                'expiring_soon' => $expiringSoon,
            ],
        ]);
    }
}
