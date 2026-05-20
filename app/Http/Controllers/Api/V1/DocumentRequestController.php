<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\DocumentRequestMail;
use App\Models\Agency;
use App\Models\DocumentRequest;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\Provider;
use App\Models\ProviderDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * DocumentRequestController — agency-initiated document asks to
 * organizations or providers.
 *
 *   Agency-facing (auth required):
 *     GET    /document-requests                list (agency-scoped)
 *     GET    /document-requests/{id}           show
 *     POST   /document-requests                create
 *     POST   /document-requests/{id}/resend    fire email again
 *     POST   /document-requests/{id}/cancel    revoke
 *
 *   Recipient-portal (auth required, role=org/provider):
 *     GET    /portal/doc-requests              list mine
 *     GET    /portal/doc-requests/{id}         show mine
 *     POST   /portal/doc-requests/{id}/upload  upload against an item
 *
 *   Public (no auth, tokenized):
 *     GET    /public/doc-requests/{token}                view request
 *     POST   /public/doc-requests/{token}/upload         upload against an item
 */
class DocumentRequestController extends Controller
{
    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('filesystems.default') === 'local' ? 'local' : 's3');
    }

    private function diskName(): string
    {
        return config('filesystems.default') === 'local' ? 'local' : 's3';
    }

    // ── AGENCY ENDPOINTS ─────────────────────────────────────────────

    /**
     * GET /document-requests
     */
    public function index(Request $request): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        $q = DocumentRequest::where('agency_id', $agencyId)
            ->with(['organization:id,name', 'provider:id,first_name,last_name', 'requester:id,first_name,last_name']);

        if ($status = $request->input('status')) $q->where('status', $status);
        if ($orgId  = $request->input('organization_id')) $q->where('organization_id', $orgId);
        if ($provId = $request->input('provider_id')) $q->where('provider_id', $provId);

        $rows = $q->orderByDesc('created_at')->limit(500)->get();

        return response()->json([
            'success' => true,
            'data'    => $rows->map(fn ($r) => $this->shape($r)),
        ]);
    }

    /**
     * GET /document-requests/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        $r = DocumentRequest::where('agency_id', $agencyId)
            ->with([
                'organization:id,name', 'provider:id,first_name,last_name',
                'requester:id,first_name,last_name',
                'organizationUploads', 'providerUploads',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->shape($r, true),
        ]);
    }

    /**
     * POST /document-requests
     * body: { organization_id?, provider_id?, recipient_email, recipient_name?,
     *         message?, items: [{key,label,required?,description?}],
     *         delivery_mode: 'portal'|'email'|'both', expires_days? }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id'  => 'nullable|integer|exists:organizations,id',
            'provider_id'      => 'nullable|integer|exists:providers,id',
            'recipient_email'  => 'required|email|max:254',
            'recipient_name'   => 'nullable|string|max:200',
            'message'          => 'nullable|string|max:4000',
            'items'            => 'required|array|min:1',
            'items.*.key'      => 'required|string|max:80',
            'items.*.label'    => 'required|string|max:200',
            'items.*.required' => 'nullable|boolean',
            'items.*.description' => 'nullable|string|max:500',
            'delivery_mode'    => 'nullable|in:portal,email,both',
            'expires_days'     => 'nullable|integer|min:1|max:365',
        ]);

        $user = $request->user();
        $agencyId = $user->effectiveAgencyId($request);
        abort_unless($agencyId, 400, 'No agency context.');

        // Polymorphic target: exactly ONE of organization_id or provider_id.
        $orgId = $request->input('organization_id');
        $providerId = $request->input('provider_id');
        if (($orgId && $providerId) || (!$orgId && !$providerId)) {
            return response()->json([
                'success' => false,
                'message' => 'Specify exactly one of organization_id or provider_id.',
            ], 422);
        }

        // Tenant guard: target row must belong to the requesting agency.
        if ($orgId) {
            Organization::where('agency_id', $agencyId)->findOrFail($orgId);
        }
        if ($providerId) {
            Provider::where('agency_id', $agencyId)->findOrFail($providerId);
        }

        $deliveryMode = $request->input('delivery_mode', 'both');
        $expiresDays = (int) ($request->input('expires_days') ?? 30);

        // Normalize item keys — lowercase + underscored — so they match
        // what we later compare against document_type on uploads.
        $items = collect($request->input('items'))
            ->map(function ($i) {
                $i['key'] = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $i['key']));
                $i['required'] = isset($i['required']) ? (bool) $i['required'] : true;
                return $i;
            })
            ->all();

        $req = DocumentRequest::create([
            'agency_id'       => $agencyId,
            'requested_by'    => $user->id,
            'organization_id' => $orgId,
            'provider_id'     => $providerId,
            'items'           => $items,
            'recipient_email' => $request->recipient_email,
            'recipient_name'  => $request->recipient_name,
            'message'         => $request->message,
            'delivery_mode'   => $deliveryMode,
            'status'          => DocumentRequest::STATUS_PENDING,
            'expires_at'      => now()->addDays($expiresDays),
        ]);

        $emailStatus = 'skipped';
        $emailError  = null;
        if ($deliveryMode === DocumentRequest::DELIVERY_EMAIL || $deliveryMode === DocumentRequest::DELIVERY_BOTH) {
            $result = $this->sendEmail($req);
            $emailStatus = $result['status'];
            $emailError  = $result['error'] ?? null;
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($this->shape($req->fresh()), [
                'email_status' => $emailStatus,
                'email_error'  => $emailError,
            ]),
        ], 201);
    }

    /**
     * POST /document-requests/{id}/resend
     */
    public function resend(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $req = DocumentRequest::where('agency_id', $agencyId)->findOrFail($id);

        if (in_array($req->status, [DocumentRequest::STATUS_CANCELLED, DocumentRequest::STATUS_FULFILLED])) {
            return response()->json([
                'success' => false,
                'message' => "Request is already {$req->status} — cannot resend.",
            ], 422);
        }

        $result = $this->sendEmail($req);
        return response()->json([
            'success' => true,
            'data' => [
                'email_status' => $result['status'],
                'email_error'  => $result['error'] ?? null,
                'email_sent_at' => $req->fresh()->email_sent_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /document-requests/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $agencyId = $request->user()->effectiveAgencyId($request);
        $req = DocumentRequest::where('agency_id', $agencyId)->findOrFail($id);

        $req->update([
            'status'       => DocumentRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);
        return response()->json(['success' => true, 'data' => $this->shape($req->fresh())]);
    }

    // ── RECIPIENT PORTAL ENDPOINTS (role=org/provider) ──────────────

    /**
     * GET /portal/doc-requests
     * Lists requests addressed to the LOGGED-IN org/provider user.
     */
    public function portalIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = DocumentRequest::query()->with(['requester:id,first_name,last_name']);

        if ($user->role === 'organization' && $user->organization_id) {
            $q->where('organization_id', $user->organization_id);
        } elseif ($user->role === 'provider' && $user->provider_id) {
            $q->where('provider_id', $user->provider_id);
        } else {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Only portal-deliverable requests for portal users — email-only
        // requests are intentionally hidden because the recipient is
        // supposed to use the tokenized URL out-of-band.
        $q->whereIn('delivery_mode', [DocumentRequest::DELIVERY_PORTAL, DocumentRequest::DELIVERY_BOTH])
          ->whereNotIn('status', [DocumentRequest::STATUS_CANCELLED]);

        // BelongsToAgency global scope is a no-op on portal users
        // (they're scoped through their own FK, not the agency's
        // tenant). Explicit scope is the org_id / provider_id above.
        $rows = $q->orderByDesc('created_at')->get();
        return response()->json([
            'success' => true,
            'data' => $rows->map(fn ($r) => $this->shape($r)),
        ]);
    }

    /**
     * GET /portal/doc-requests/{id}
     */
    public function portalShow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $req = $this->loadPortalRequest($user, $id);
        if (!$req) return response()->json(['success' => false, 'message' => 'Not found.'], 404);

        // First-view tracking
        if (!$req->first_viewed_at) {
            $req->forceFill(['first_viewed_at' => now()])->saveQuietly();
        }
        return response()->json(['success' => true, 'data' => $this->shape($req->fresh(), true)]);
    }

    /**
     * POST /portal/doc-requests/{id}/upload
     */
    public function portalUpload(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file'     => 'required|file|max:20480',
            'item_key' => 'required|string|max:80',
        ]);
        $user = $request->user();
        $req = $this->loadPortalRequest($user, $id);
        if (!$req) return response()->json(['success' => false, 'message' => 'Not found.'], 404);

        return $this->ingestUpload($request, $req);
    }

    // ── PUBLIC ENDPOINTS (no auth, tokenized) ────────────────────────

    /**
     * GET /public/doc-requests/{token}
     */
    public function publicShow(Request $request, string $token): JsonResponse
    {
        $req = $this->resolveTokenOrFail($token);
        // First-view tracking
        if (!$req->first_viewed_at) {
            $req->forceFill(['first_viewed_at' => now()])->saveQuietly();
        }

        // Return a SHAPED, REDACTED view — no agency internals, no
        // user IDs, just what the recipient needs to fulfil the ask.
        return response()->json([
            'success' => true,
            'data' => [
                'id'              => $req->id,
                'status'          => $req->status,
                'items'           => $req->items ?? [],
                'recipient_email' => $req->recipient_email,
                'recipient_name'  => $req->recipient_name,
                'message'         => $req->message,
                'expires_at'      => $req->expires_at?->toIso8601String(),
                'agency_name'     => $req->agency?->company_display_name ?: $req->agency?->name,
                'target_label'    => $req->organization?->name ?: trim(($req->provider?->first_name . ' ' . $req->provider?->last_name)),
                // Per-item fulfilment so the upload UI knows what's
                // still outstanding.
                'uploaded_keys'   => array_unique(array_merge(
                    $req->organizationUploads->pluck('document_type')->all(),
                    $req->providerUploads->pluck('document_type')->all(),
                )),
            ],
        ]);
    }

    /**
     * POST /public/doc-requests/{token}/upload
     */
    public function publicUpload(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'file'     => 'required|file|max:20480',
            'item_key' => 'required|string|max:80',
        ]);
        $req = $this->resolveTokenOrFail($token);
        return $this->ingestUpload($request, $req);
    }

    // ── INTERNAL HELPERS ─────────────────────────────────────────────

    private function loadPortalRequest($user, int $id): ?DocumentRequest
    {
        $q = DocumentRequest::query()->with([
            'requester:id,first_name,last_name', 'organization:id,name',
            'provider:id,first_name,last_name', 'organizationUploads', 'providerUploads',
        ])->where('id', $id)
          ->whereIn('delivery_mode', [DocumentRequest::DELIVERY_PORTAL, DocumentRequest::DELIVERY_BOTH]);

        if ($user->role === 'organization' && $user->organization_id) {
            $q->where('organization_id', $user->organization_id);
        } elseif ($user->role === 'provider' && $user->provider_id) {
            $q->where('provider_id', $user->provider_id);
        } else {
            return null;
        }
        return $q->first();
    }

    private function resolveTokenOrFail(string $token): DocumentRequest
    {
        $req = DocumentRequest::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('public_token', $token)
            ->with(['agency:id,name,company_display_name,primary_color', 'organization:id,name', 'provider:id,first_name,last_name', 'organizationUploads', 'providerUploads'])
            ->first();
        abort_unless($req, 404);
        abort_if($req->isExpired(), 410, 'This request has expired.');
        abort_if($req->status === DocumentRequest::STATUS_CANCELLED, 410, 'This request was cancelled.');
        return $req;
    }

    /**
     * Common ingest path for portal + public uploads. Writes to R2,
     * creates the org/provider document row, links it to the request,
     * and recomputes status.
     */
    private function ingestUpload(Request $request, DocumentRequest $req): JsonResponse
    {
        $item = collect($req->items ?? [])->firstWhere('key', $request->item_key);
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => "Unknown item key '{$request->item_key}' for this request.",
            ], 422);
        }

        $file = $request->file('file');
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());

        // Route to the right entity-scoped storage bucket.
        if ($req->organization_id) {
            $path = "organization-documents/{$req->agency_id}/{$req->organization_id}/{$filename}";
        } else {
            $path = "documents/{$req->agency_id}/{$req->provider_id}/{$filename}";
        }

        try {
            $this->disk()->put($path, file_get_contents($file->getRealPath()));
        } catch (\Throwable $e) {
            Log::error('doc-request upload R2 put failed', ['req_id' => $req->id, 'err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Storage unavailable — please retry.'], 503);
        }

        $payload = [
            'agency_id'         => $req->agency_id,
            'document_type'     => $request->item_key,  // matches item.key for fulfilment check
            'document_name'     => $item['label'] ?? $request->item_key,
            'file_path'         => $path,
            'file_disk'         => $this->diskName(),
            'mime_type'         => $file->getClientMimeType(),
            'file_size'         => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'uploaded_by'       => $request->user()?->id,
            'status'            => 'received',
            'received_date'     => now()->toDateString(),
            'document_request_id' => $req->id,
        ];

        DB::transaction(function () use ($req, $payload) {
            if ($req->organization_id) {
                $payload['organization_id'] = $req->organization_id;
                OrganizationDocument::create($payload);
            } else {
                $payload['provider_id'] = $req->provider_id;
                ProviderDocument::create($payload);
            }
            $req->fresh()->recomputeStatus();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'item_key' => $request->item_key,
                'request_status' => $req->fresh()->status,
            ],
        ]);
    }

    private function sendEmail(DocumentRequest $req): array
    {
        try {
            $agency = Agency::find($req->agency_id);
            if (!$agency) return ['status' => 'failed', 'error' => 'Agency record missing.'];

            $publicUrl = rtrim(config('app.frontend_url', config('app.url')), '/')
                . '/#/portal/doc-request/' . $req->public_token;

            Mail::to($req->recipient_email)->send(new DocumentRequestMail($req, $publicUrl, $agency));
            $req->forceFill(['email_sent_at' => now()])->saveQuietly();
            return ['status' => 'sent'];
        } catch (\Throwable $e) {
            Log::error('document-request email send failed', ['req_id' => $req->id, 'err' => $e->getMessage()]);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Standardized JSON shape for both agency and recipient views.
     * $full=true includes attached uploads.
     */
    private function shape(DocumentRequest $r, bool $full = false): array
    {
        $items = $r->items ?? [];
        $uploadedTypes = array_unique(array_merge(
            ($r->relationLoaded('organizationUploads') ? $r->organizationUploads : collect())->pluck('document_type')->all(),
            ($r->relationLoaded('providerUploads')     ? $r->providerUploads     : collect())->pluck('document_type')->all(),
        ));
        $base = [
            'id'                => $r->id,
            'status'            => $r->status,
            'delivery_mode'     => $r->delivery_mode,
            'recipient_email'   => $r->recipient_email,
            'recipient_name'    => $r->recipient_name,
            'message'           => $r->message,
            'items'             => $items,
            'item_count'        => count($items),
            'fulfilled_count'   => count(array_intersect(collect($items)->pluck('key')->all(), $uploadedTypes)),
            'organization_id'   => $r->organization_id,
            'organization_name' => $r->organization?->name,
            'provider_id'       => $r->provider_id,
            'provider_name'     => $r->provider ? trim("{$r->provider->first_name} {$r->provider->last_name}") : null,
            'requester_name'    => $r->requester ? trim("{$r->requester->first_name} {$r->requester->last_name}") : null,
            'public_token'      => $r->public_token,
            'email_sent_at'     => $r->email_sent_at?->toIso8601String(),
            'first_viewed_at'   => $r->first_viewed_at?->toIso8601String(),
            'first_uploaded_at' => $r->first_uploaded_at?->toIso8601String(),
            'fulfilled_at'      => $r->fulfilled_at?->toIso8601String(),
            'expires_at'        => $r->expires_at?->toIso8601String(),
            'created_at'        => $r->created_at?->toIso8601String(),
        ];
        if ($full) {
            $base['organization_uploads'] = $r->relationLoaded('organizationUploads') ? $r->organizationUploads : [];
            $base['provider_uploads']     = $r->relationLoaded('providerUploads') ? $r->providerUploads : [];
        }
        return $base;
    }
}
