<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationAttachmentController extends Controller
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
     * List all attachments for an application.
     */
    public function index(Request $request, int $applicationId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);
        Application::where('agency_id', $agencyId)->findOrFail($applicationId);

        $attachments = ApplicationAttachment::where('agency_id', $agencyId)
            ->where('application_id', $applicationId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments,
        ]);
    }

    /**
     * Upload a file attachment to an application.
     */
    public function store(Request $request, int $applicationId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20MB
            'label' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        $agencyId = $this->resolveAgencyId($request);

        Application::where('agency_id', $agencyId)->findOrFail($applicationId);

        $file = $request->file('file');
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = "attachments/{$agencyId}/applications/{$applicationId}/{$filename}";

        $this->disk()->put($path, file_get_contents($file->getRealPath()));

        $attachment = ApplicationAttachment::create([
            'agency_id' => $agencyId,
            'application_id' => $applicationId,
            'label' => $request->label ?: $file->getClientOriginalName(),
            'notes' => $request->notes,
            'file_path' => $path,
            'file_disk' => config('filesystems.default') === 'local' ? 'local' : 's3',
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $user->id,
        ]);

        return response()->json(['success' => true, 'data' => $attachment], 201);
    }

    /**
     * Download an attachment.
     */
    public function download(Request $request, int $applicationId, int $attachmentId): JsonResponse|StreamedResponse
    {
        $agencyId = $this->resolveAgencyId($request);

        $attachment = ApplicationAttachment::where('agency_id', $agencyId)
            ->where('application_id', $applicationId)
            ->findOrFail($attachmentId);

        if (!$attachment->file_path) {
            return response()->json(['success' => false, 'message' => 'No file attached'], 404);
        }

        $disk = Storage::disk($attachment->file_disk ?? 's3');

        if (!$disk->exists($attachment->file_path)) {
            return response()->json(['success' => false, 'message' => 'File not found in storage'], 404);
        }

        if ($attachment->file_disk !== 'local') {
            $url = $disk->temporaryUrl($attachment->file_path, now()->addMinutes(15));
            return response()->json(['success' => true, 'data' => ['url' => $url, 'filename' => $attachment->original_name]]);
        }

        return $disk->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Request $request, int $applicationId, int $attachmentId): JsonResponse
    {
        $agencyId = $this->resolveAgencyId($request);

        $attachment = ApplicationAttachment::where('agency_id', $agencyId)
            ->where('application_id', $applicationId)
            ->findOrFail($attachmentId);

        if ($attachment->file_path) {
            $disk = Storage::disk($attachment->file_disk ?? 's3');
            if ($disk->exists($attachment->file_path)) {
                $disk->delete($attachment->file_path);
            }
        }

        $attachment->delete();

        return response()->json(['success' => true]);
    }
}
