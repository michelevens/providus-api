<?php

namespace App\Http\Controllers\Api\V1;

// Denial appeal-letter supporting documentation. Operators attach
// clinical notes, prior auth letters, EOBs, EOMBs, medical-necessity
// support, anything else the payer requires.
//
// Storage: Cloudflare R2 (S3-compatible). Disk config in
// config/filesystems.php under 'r2'. Env vars set on Railway per the
// Phase 5C step. Bucket is private — attachment URLs returned to V2
// are short-lived signed URLs (10-minute TTL) generated on demand.
// No public bucket URL because the contents are PHI-adjacent.
//
// Metadata lives on the denial: ClaimDenial::$attachments is a
// jsonb array of {key, label, content_type, size_bytes, uploaded_at,
// uploaded_by_id, uploaded_by_name}. The 'key' is the R2 object
// key (the only thing the operator's view actually requires; the
// signed URL is recomputed at list-time so we don't store stale or
// leaked URLs in the database).
//
// Why a dedicated controller (not another RcmController method):
// upload/list/delete are tightly grouped, the upload path needs
// validation rules that don't fit RcmController's pattern, and a
// separate file means a single grep target when we extend to
// payments/claims attachments later.

use App\Http\Controllers\Controller;
use App\Models\ClaimDenial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DenialAttachmentController extends Controller
{
    // R2 disk name (configured in config/filesystems.php).
    private const DISK = 'r2';

    // Signed URL TTL — short enough to be safe if a copy leaks, long
    // enough for the operator to click through after the list loads.
    private const SIGNED_URL_TTL_MINUTES = 10;

    // Upload limits. Hard cap is enforced both client- and server-side.
    private const MAX_BYTES_PER_FILE = 25 * 1024 * 1024; // 25 MB
    private const MAX_FILES_PER_REQUEST = 10;

    // Allowed mime types — narrower than what S3 will accept, broader
    // than just images. Tightened to what payers actually accept for
    // appeal-supporting docs.
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
        'image/heic',
        'image/heif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // .xlsx
        'text/plain',
        'text/csv',
    ];

    /**
     * GET /rcm/denials/{id}/attachments
     *
     * Returns the attachment list with freshly-signed download URLs.
     * URLs expire after self::SIGNED_URL_TTL_MINUTES — don't cache
     * them on the client. Re-fetch the list to refresh.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $denial = $this->findDenial($request, $id);
        $attachments = $this->signedAttachments($denial);

        return response()->json([
            'success' => true,
            'data'    => $attachments,
        ]);
    }

    /**
     * POST /rcm/denials/{id}/attachments
     *
     * Multipart upload. Field name: `files[]` (array of files) or a
     * single `file`. Optional `labels[]` matching `files[]` index;
     * each label defaults to the original filename if missing.
     *
     * Response: 201 + the full updated attachment list (signed URLs
     * included) so V2 can re-render without a separate GET.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $denial = $this->findDenial($request, $id);

        // Accept both `files[]` and a single `file` for ergonomics.
        $files = $request->file('files');
        if (!$files && $request->hasFile('file')) {
            $files = [$request->file('file')];
        }
        if (!is_array($files)) {
            $files = $files ? [$files] : [];
        }
        if (empty($files)) {
            return response()->json([
                'success' => false,
                'error'   => 'no_files',
                'message' => 'No file(s) uploaded. Use multipart/form-data with field "files[]" or "file".',
            ], 422);
        }
        if (count($files) > self::MAX_FILES_PER_REQUEST) {
            return response()->json([
                'success' => false,
                'error'   => 'too_many_files',
                'message' => 'Maximum ' . self::MAX_FILES_PER_REQUEST . ' files per upload.',
            ], 422);
        }

        $labels = (array) $request->input('labels', []);
        $user = $request->user();
        $uploadedByName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->email ?? null);

        $existing = is_array($denial->attachments) ? $denial->attachments : [];
        $added = [];

        foreach ($files as $i => $file) {
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'invalid_upload',
                    'message' => 'Upload failed at index ' . $i . ': ' . ($file ? $file->getErrorMessage() : 'no file'),
                ], 422);
            }
            $size = $file->getSize();
            if ($size === false || $size > self::MAX_BYTES_PER_FILE) {
                return response()->json([
                    'success' => false,
                    'error'   => 'file_too_large',
                    'message' => 'File "' . $file->getClientOriginalName() . '" exceeds 25 MB.',
                ], 422);
            }
            $mime = $file->getMimeType() ?: 'application/octet-stream';
            if (!in_array($mime, self::ALLOWED_MIMES, true)) {
                return response()->json([
                    'success' => false,
                    'error'   => 'mime_not_allowed',
                    'message' => 'File type ' . $mime . ' is not permitted. Allowed: PDF, images, Office docs, plain text/CSV.',
                ], 422);
            }

            // R2 object key. Scoped by agency + denial so a leaked
            // R2 secret can only see one tenant's files (when paired
            // with bucket-policy guards) and so list-on-prefix works.
            $original = $file->getClientOriginalName() ?: 'file';
            $safeName = $this->sanitizeFilename($original);
            $uuid = (string) Str::uuid();
            $key = sprintf(
                'denial-attachments/%d/%d/%s-%s',
                $denial->agency_id,
                $denial->id,
                $uuid,
                $safeName
            );

            $stream = fopen($file->getRealPath(), 'rb');
            try {
                Storage::disk(self::DISK)->put($key, $stream, [
                    'visibility' => 'private',
                    'ContentType' => $mime,
                ]);
            } finally {
                if (is_resource($stream)) fclose($stream);
            }

            $meta = [
                'key'                => $key,
                'label'              => trim((string) ($labels[$i] ?? '')) ?: $safeName,
                'content_type'       => $mime,
                'size_bytes'         => (int) $size,
                'uploaded_at'        => now()->toIso8601String(),
                'uploaded_by_id'     => $user->id ?? null,
                'uploaded_by_name'   => $uploadedByName,
            ];
            $existing[] = $meta;
            $added[] = $meta;
        }

        $denial->attachments = $existing;
        $denial->save();

        Log::info('denial.attachment.uploaded', [
            'denial_id' => $denial->id,
            'agency_id' => $denial->agency_id,
            'user_id'   => $user->id ?? null,
            'count'     => count($added),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->signedAttachments($denial->fresh()),
            'added'   => $added, // unsigned metadata — V2 doesn't need URLs here
        ], 201);
    }

    /**
     * DELETE /rcm/denials/{id}/attachments?key=denial-attachments/...
     *
     * Removes the R2 object and the matching attachments[] entry.
     * The full R2 object key goes in the `key` query param (the
     * signed URL the client has is enough to identify it; we don't
     * use a separate per-attachment ID to avoid a join table).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $denial = $this->findDenial($request, $id);
        $key = (string) $request->query('key', '');

        if ($key === '') {
            return response()->json([
                'success' => false,
                'error'   => 'missing_key',
                'message' => 'Provide ?key=<r2-object-key> identifying the attachment to remove.',
            ], 422);
        }

        // Belt-and-suspenders: a stolen agency token must not be able
        // to delete from a different agency's prefix. The key must
        // start with this denial's specific prefix.
        $expectedPrefix = sprintf('denial-attachments/%d/%d/', $denial->agency_id, $denial->id);
        if (!str_starts_with($key, $expectedPrefix)) {
            return response()->json([
                'success' => false,
                'error'   => 'key_scope_mismatch',
                'message' => 'Attachment key does not belong to this denial.',
            ], 403);
        }

        $existing = is_array($denial->attachments) ? $denial->attachments : [];
        $remaining = array_values(array_filter($existing, fn ($a) => ($a['key'] ?? null) !== $key));

        if (count($remaining) === count($existing)) {
            return response()->json([
                'success' => false,
                'error'   => 'attachment_not_found',
                'message' => 'No attachment matched that key on this denial.',
            ], 404);
        }

        // Best-effort R2 delete. If the object is already gone we
        // still want the metadata removed, so swallow Storage errors
        // here (logged for ops visibility).
        try {
            Storage::disk(self::DISK)->delete($key);
        } catch (\Throwable $e) {
            Log::warning('denial.attachment.r2_delete_failed', [
                'denial_id' => $denial->id,
                'key'       => $key,
                'error'     => $e->getMessage(),
            ]);
        }

        $denial->attachments = $remaining;
        $denial->save();

        return response()->json([
            'success' => true,
            'data'    => $this->signedAttachments($denial->fresh()),
        ]);
    }

    /**
     * Load + authorize the denial. Same agency scoping as the rest
     * of the denial endpoints (RcmController::denialPdf, etc).
     */
    private function findDenial(Request $request, int $id): ClaimDenial
    {
        return ClaimDenial::where('agency_id', $request->user()->effectiveAgencyId($request))
            ->findOrFail($id);
    }

    /**
     * Decorate the stored attachment metadata with a fresh signed
     * download URL. Doesn't mutate the model — view-layer concern.
     */
    private function signedAttachments(ClaimDenial $denial): array
    {
        $attachments = is_array($denial->attachments) ? $denial->attachments : [];
        $disk = Storage::disk(self::DISK);
        $ttl = now()->addMinutes(self::SIGNED_URL_TTL_MINUTES);
        return array_map(function ($a) use ($disk, $ttl) {
            $key = $a['key'] ?? null;
            $signedUrl = null;
            if ($key) {
                try {
                    $signedUrl = $disk->temporaryUrl($key, $ttl);
                } catch (\Throwable $e) {
                    // If the disk isn't configured (R2 creds missing
                    // before Phase 5C is finished) return the metadata
                    // without a URL rather than 500ing the list.
                    Log::warning('denial.attachment.sign_failed', [
                        'key'   => $key,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return array_merge($a, ['signed_url' => $signedUrl]);
        }, $attachments);
    }

    /**
     * Strip directory parts, normalize unicode, keep only filesystem-
     * and URL-safe characters. Always returns a non-empty string.
     */
    private function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        // Replace anything outside [A-Za-z0-9._-] with _
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'file';
        $clean = trim($clean, '._-');
        return $clean !== '' ? $clean : 'file';
    }
}
