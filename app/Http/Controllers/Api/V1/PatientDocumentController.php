<?php

namespace App\Http\Controllers\Api\V1;

// PatientDocumentController — file storage for patient-scoped artifacts
// (insurance cards, intake forms, signed consents, paper EOBs). Mirrors
// the provider-side DocumentController but keys by patient_key
// (lowercased + trimmed patient_name) since V2 doesn't model patients
// as first-class rows.

use App\Http\Controllers\Controller;
use App\Models\PatientDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientDocumentController extends Controller
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
     * Normalize the URL-encoded patient key. Defensively re-lowercases
     * so callers that hit the API directly don't bypass the convention.
     */
    private function normalizeKey(string $key): string
    {
        $k = mb_strtolower(trim(urldecode($key)));
        abort_if($k === '', 422, 'Empty patient key');
        return $k;
    }

    /**
     * GET /rcm/patients/{key}/documents
     */
    public function index(Request $request, string $key): JsonResponse
    {
        $agencyId   = $this->resolveAgencyId($request);
        $patientKey = $this->normalizeKey($key);

        $docs = PatientDocument::where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
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
     * POST /rcm/patients/{key}/documents/upload
     * multipart/form-data with `file`.
     */
    public function upload(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'file'            => 'required|file|max:20480', // 20 MB
            'document_type'   => 'required|string|max:100',
            'document_name'   => 'required|string|max:200',
            'expiration_date' => 'nullable|date',
            'notes'           => 'nullable|string|max:2000',
        ]);

        $agencyId   = $this->resolveAgencyId($request);
        $patientKey = $this->normalizeKey($key);

        $file = $request->file('file');
        // Storage path uses md5 of the key so we don't drop raw PII in
        // R2 object paths. Same agency_id scoping the rest of the app uses.
        $keyHash = substr(md5($patientKey), 0, 12);
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = "patient-documents/{$agencyId}/{$keyHash}/{$filename}";

        // Wrap R2 put: a network blip or expired token throws and the
        // request 500s with a stack trace if uncaught. We surface a
        // clean 503 instead so the client knows the file didn't land
        // and no DB row exists for a phantom upload.
        try {
            $this->disk()->put($path, file_get_contents($file->getRealPath()));
        } catch (\Throwable $e) {
            Log::error('patient-document R2 put failed', ['agency_id' => $agencyId, 'path' => $path, 'err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Storage unavailable — please retry.'], 503);
        }

        $doc = PatientDocument::create([
            'agency_id'         => $agencyId,
            'patient_key'       => $patientKey,
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
        ]);

        return response()->json(['success' => true, 'data' => $doc], 201);
    }

    /**
     * GET /rcm/patients/{key}/documents/{id}/download
     * For R2: returns a 15-minute presigned URL. For local disk: streams.
     */
    public function download(Request $request, string $key, int $id): JsonResponse|StreamedResponse
    {
        $agencyId   = $this->resolveAgencyId($request);
        $patientKey = $this->normalizeKey($key);

        $doc = PatientDocument::where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
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
     * PUT /rcm/patients/{key}/documents/{id}
     * Update metadata (not file). For file replacement use upload again.
     */
    public function update(Request $request, string $key, int $id): JsonResponse
    {
        $request->validate([
            'document_type'   => 'sometimes|string|max:100',
            'document_name'   => 'sometimes|string|max:200',
            'status'          => 'sometimes|string|max:20',
            'expiration_date' => 'sometimes|nullable|date',
            'notes'           => 'sometimes|nullable|string|max:2000',
        ]);
        $agencyId   = $this->resolveAgencyId($request);
        $patientKey = $this->normalizeKey($key);

        $doc = PatientDocument::where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
            ->findOrFail($id);
        $doc->update($request->only(['document_type', 'document_name', 'status', 'expiration_date', 'notes']));
        return response()->json(['success' => true, 'data' => $doc->fresh()]);
    }

    /**
     * DELETE /rcm/patients/{key}/documents/{id}
     */
    public function destroy(Request $request, string $key, int $id): JsonResponse
    {
        $agencyId   = $this->resolveAgencyId($request);
        $patientKey = $this->normalizeKey($key);

        $doc = PatientDocument::where('agency_id', $agencyId)
            ->where('patient_key', $patientKey)
            ->findOrFail($id);

        if ($doc->file_path) {
            $disk = Storage::disk($doc->file_disk ?? 's3');
            if ($disk->exists($doc->file_path)) {
                $disk->delete($doc->file_path);
            }
        }
        $doc->delete();
        return response()->json(['success' => true]);
    }
}
