<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A write-off awaiting org or owner approval.
 *
 * Created when WriteOffApproval::decide() returns org_required or
 * owner_required. Carries the proposed amount/reason/category plus
 * the routing target (org email + signed portal token, or null email
 * for owner-queue items that ride on billing_tasks).
 *
 * Lifecycle:
 *   pending → approved   (decided via portal click or owner queue)
 *   pending → rejected   (decided via portal click or owner queue)
 *   pending → expired    (fallback window elapsed; cron auto-escalates)
 *   pending → escalated_to_owner (expired AND auto-converted to a
 *                                 billing_tasks owner approval)
 *   pending → cancelled  (agency operator withdrew)
 */
class WriteOffRequest extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'agency_id', 'claim_id', 'billing_client_id',
        'amount', 'category', 'reason',
        'requested_by', 'requested_at',
        'approver_type', 'approver_email', 'portal_token', 'expires_at',
        'status', 'decided_at', 'decided_by_email', 'decided_by_user_id',
        'decision_reason', 'applied_at',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'requested_at'  => 'datetime',
        'expires_at'    => 'datetime',
        'decided_at'    => 'datetime',
        'applied_at'    => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function billingClient(): BelongsTo
    {
        return $this->belongsTo(BillingClient::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** Generate a URL-safe 64-char token for the org's portal link. */
    public static function freshPortalToken(): string
    {
        // 48 random bytes → 64 char URL-safe base64. Strip padding +
        // swap +/= for -_ so it's safe in a URL without encoding.
        $raw = random_bytes(48);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** Scope: rows that are still actionable (waiting on someone). */
    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    /** Scope: rows whose org-approval window has lapsed. Used by
     *  the expirer job to escalate org_required → owner queue. */
    public function scopeExpired($q)
    {
        return $q->where('status', 'pending')
                 ->where('approver_type', 'org')
                 ->whereNotNull('expires_at')
                 ->where('expires_at', '<', now());
    }
}
