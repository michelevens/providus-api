<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Agency → org/provider document-request lifecycle. See migration
 * header for the full schema rationale.
 *
 * BelongsToAgency adds the TenantScope so an authenticated session
 * can't read another agency's requests. The public token route opts
 * out of the global scope explicitly (same pattern as ServiceLineShareLink).
 */
class DocumentRequest extends Model
{
    use BelongsToAgency, HasFactory, SoftDeletes;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    public const DELIVERY_PORTAL = 'portal';
    public const DELIVERY_EMAIL  = 'email';
    public const DELIVERY_BOTH   = 'both';

    protected $fillable = [
        'agency_id', 'requested_by',
        'organization_id', 'provider_id',
        'items', 'recipient_email', 'recipient_name', 'message',
        'public_token', 'delivery_mode', 'status',
        'email_sent_at', 'first_viewed_at', 'first_uploaded_at',
        'fulfilled_at', 'cancelled_at', 'cancelled_by',
        'expires_at',
    ];

    protected $casts = [
        'items'              => 'array',
        'email_sent_at'      => 'datetime',
        'first_viewed_at'    => 'datetime',
        'first_uploaded_at'  => 'datetime',
        'fulfilled_at'       => 'datetime',
        'cancelled_at'       => 'datetime',
        'expires_at'         => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DocumentRequest $r) {
            if (empty($r->public_token)) {
                $r->public_token = Str::random(40);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Uploads attached to this request, regardless of which side
     * (org or provider) owns them. Used by the lifecycle recomputer
     * to decide if status should flip to partial/fulfilled.
     */
    public function organizationUploads(): HasMany
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    public function providerUploads(): HasMany
    {
        return $this->hasMany(ProviderDocument::class);
    }

    public function isExpired(): bool
    {
        // 1-year hard cap regardless of explicit expires_at (defense-in-depth).
        if ($this->created_at && $this->created_at->lt(now()->subYear())) {
            return true;
        }
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Recompute status from current upload state. Called after any
     * upload or cancel transaction. The status field is the source of
     * truth for "what state is this request in" — the UI never
     * recomputes from items+uploads on the fly.
     */
    public function recomputeStatus(): void
    {
        if (in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_EXPIRED])) {
            return; // terminal
        }
        $itemKeys = collect($this->items ?? [])->pluck('key')->filter()->unique();
        if ($itemKeys->isEmpty()) {
            return; // empty request — leave as pending
        }
        $orgUploadTypes = $this->organizationUploads()->pluck('document_type')->toArray();
        $provUploadTypes = $this->providerUploads()->pluck('document_type')->toArray();
        $allUploadedTypes = array_merge($orgUploadTypes, $provUploadTypes);
        // An "item" is fulfilled if at least one upload's document_type
        // matches its key. The upload UI writes document_type=key so the
        // join is direct.
        $fulfilledCount = $itemKeys->filter(fn ($k) => in_array($k, $allUploadedTypes))->count();
        $total = $itemKeys->count();
        $newStatus = $fulfilledCount === 0
            ? self::STATUS_PENDING
            : ($fulfilledCount >= $total ? self::STATUS_FULFILLED : self::STATUS_PARTIAL);
        $updates = ['status' => $newStatus];
        if (!$this->first_uploaded_at && $fulfilledCount > 0) {
            $updates['first_uploaded_at'] = now();
        }
        if ($newStatus === self::STATUS_FULFILLED && !$this->fulfilled_at) {
            $updates['fulfilled_at'] = now();
        }
        $this->update($updates);
    }
}
