<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ServiceLinePlanShared;
use App\Models\Agency;
use App\Models\ServiceLineShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Service-line business-plan share links.
 *
 *  - store():       authenticated agency user uploads the rendered PDF
 *                   bytes + recipient details. We persist to R2, mint a
 *                   token, and (optionally) fire a Resend email. Returns
 *                   the public URL so the frontend can copy/share it.
 *  - publicShow():  unauthenticated. Bumps view_count and 302-redirects
 *                   to a 15-minute presigned R2 URL. Throttled.
 *  - index() / destroy(): authenticated. List links for an agency, revoke.
 *
 * The PDF lives in R2 because (a) recipients can re-download, (b)
 * agencies can re-send the same link, (c) the V2 catalog evolves and
 * we want recipients to see the plan as it was when sent — a snapshot.
 */
class ServiceLineShareController extends Controller
{
    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('filesystems.default') === 'local' ? 'local' : 's3');
    }

    private function diskName(): string
    {
        return config('filesystems.default') === 'local' ? 'local' : 's3';
    }

    /**
     * POST /service-line-plans/share
     * multipart/form-data with `file` (the rendered PDF) + recipient
     * details. Caller must already be authenticated.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'              => 'required|file|mimes:pdf|max:20480', // 20MB
            'service_line_id'   => 'required|string|max:80',
            'service_line_name' => 'required|string|max:200',
            'recipient_email'   => 'nullable|email|max:254',
            'recipient_name'    => 'nullable|string|max:200',
            'message'           => 'nullable|string|max:4000',
            'organization_id'   => 'nullable|integer|exists:organizations,id',
            'expires_days'      => 'nullable|integer|min:1|max:365',
            'send_email'        => 'nullable|boolean',
        ]);

        $user = $request->user();
        $agencyId = $user->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        // Store under a per-agency, per-line prefix so cleanup/audit is
        // easy and a misconfigured signing key can't cross tenants.
        $file = $request->file('file');
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $r2Key = "service-line-plans/{$agencyId}/{$request->service_line_id}/{$filename}";
        $this->disk()->put($r2Key, file_get_contents($file->getRealPath()));

        $expiresDays = (int) ($request->input('expires_days') ?? 90);

        $link = ServiceLineShareLink::create([
            'agency_id'          => $agencyId,
            'sender_user_id'     => $user->id,
            'service_line_id'    => $request->service_line_id,
            'service_line_name'  => $request->service_line_name,
            'organization_id'    => $request->organization_id,
            'recipient_email'    => $request->recipient_email,
            'recipient_name'     => $request->recipient_name,
            'message'            => $request->message,
            'r2_key'             => $r2Key,
            'original_filename'  => $file->getClientOriginalName(),
            'file_size'          => $file->getSize(),
            'file_disk'          => $this->diskName(),
            'expires_at'         => now()->addDays($expiresDays),
        ]);

        $publicUrl = $this->buildPublicUrl($link->public_token);

        $shouldSend = $request->boolean('send_email', true) && !empty($request->recipient_email);
        if ($shouldSend) {
            $this->sendEmail($link, $publicUrl);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'             => $link->id,
                'public_token'   => $link->public_token,
                'public_url'     => $publicUrl,
                'expires_at'     => $link->expires_at?->toIso8601String(),
                'email_sent_at'  => $link->email_sent_at?->toIso8601String(),
                'recipient_email'=> $link->recipient_email,
                'view_count'     => $link->view_count,
            ],
        ], 201);
    }

    /**
     * GET /public/service-line-plans/{token}
     * Unauthenticated. 302 to a 15-minute presigned R2 URL.
     */
    public function publicShow(Request $request, string $token)
    {
        $link = ServiceLineShareLink::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('public_token', $token)
            ->first();

        if (!$link) {
            return response()->json(['success' => false, 'message' => 'Link not found.'], 404);
        }
        if ($link->isExpired()) {
            return response()->json(['success' => false, 'message' => 'This link has expired.'], 410);
        }

        // Bump tracking. updateQuietly to avoid an updated_at touch
        // creating a false "modified" timestamp on every view.
        $link->forceFill([
            'view_count'     => $link->view_count + 1,
            'last_viewed_at' => now(),
            'last_viewed_ip' => substr((string) $request->ip(), 0, 64),
        ])->saveQuietly();

        $disk = Storage::disk($link->file_disk ?? 's3');
        if (!$disk->exists($link->r2_key)) {
            Log::warning('service-line plan PDF missing from storage', ['link_id' => $link->id, 'r2_key' => $link->r2_key]);
            return response()->json(['success' => false, 'message' => 'Plan file no longer available.'], 410);
        }

        if (($link->file_disk ?? 's3') !== 'local') {
            $url = $disk->temporaryUrl($link->r2_key, now()->addMinutes(15), [
                'ResponseContentDisposition' => 'inline; filename="' . addslashes($link->original_filename) . '"',
            ]);
            return redirect()->away($url);
        }

        return $disk->download($link->r2_key, $link->original_filename);
    }

    /**
     * GET /service-line-plans/links
     * Authenticated list, optionally filtered by service_line_id.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $agencyId = $user->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        $q = ServiceLineShareLink::where('agency_id', $agencyId);
        if ($request->filled('service_line_id')) {
            $q->where('service_line_id', $request->service_line_id);
        }
        $rows = $q->orderByDesc('created_at')->limit(200)->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($l) => [
                'id'              => $l->id,
                'service_line_id' => $l->service_line_id,
                'service_line_name' => $l->service_line_name,
                'recipient_email' => $l->recipient_email,
                'recipient_name'  => $l->recipient_name,
                'organization_id' => $l->organization_id,
                'public_url'      => $this->buildPublicUrl($l->public_token),
                'view_count'      => $l->view_count,
                'last_viewed_at'  => $l->last_viewed_at?->toIso8601String(),
                'email_sent_at'   => $l->email_sent_at?->toIso8601String(),
                'expires_at'      => $l->expires_at?->toIso8601String(),
                'created_at'      => $l->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * DELETE /service-line-plans/links/{id}
     * Revokes the link by deleting the row (and the R2 object).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $agencyId = $user->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        $link = ServiceLineShareLink::where('agency_id', $agencyId)->findOrFail($id);

        try {
            Storage::disk($link->file_disk ?? 's3')->delete($link->r2_key);
        } catch (\Throwable $e) {
            Log::warning('failed to delete service-line plan from storage', ['link_id' => $link->id, 'err' => $e->getMessage()]);
        }
        $link->delete();

        return response()->json(['success' => true]);
    }

    private function buildPublicUrl(string $token): string
    {
        // The public download is served by THIS api (302 → presigned R2).
        // Frontend has no separate landing page — the link is the file.
        return rtrim(config('app.url'), '/') . '/api/public/service-line-plans/' . $token;
    }

    private function sendEmail(ServiceLineShareLink $link, string $publicUrl): void
    {
        try {
            $agency = Agency::find($link->agency_id);
            if (!$agency) {
                Log::warning('service-line plan email skipped — agency missing', ['link_id' => $link->id]);
                return;
            }
            Mail::to($link->recipient_email)->send(new ServiceLinePlanShared($link, $publicUrl, $agency));
            $link->forceFill(['email_sent_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            // Don't fail the API call if the mail provider hiccups —
            // the link is still valid and copy-share works. Log so
            // we can surface a "resend" affordance later.
            Log::error('service-line plan email send failed', ['link_id' => $link->id, 'err' => $e->getMessage()]);
        }
    }
}
