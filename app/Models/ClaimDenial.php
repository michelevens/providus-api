<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimDenial extends Model
{
    use Auditable, BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'claim_id', 'billing_client_id', 'denial_category', 'denial_code',
        'denial_reason', 'denied_amount', 'status', 'priority', 'denial_date',
        'appeal_deadline', 'appeal_level', 'appeal_submitted_date', 'recovered_amount',
        'appeal_notes', 'resolution_notes', 'assigned_to', 'created_by', 'resolved_at',
        // Phase 1A (2026-05-15): full denial-workflow fields. See
        // migration 2026_05_15_180000_extend_claim_denials_for_workflow.
        'triaged_at', 'triaged_by',
        'letter_text', 'letter_drafted_at', 'letter_drafted_by',
        'letter_sent_at', 'letter_sent_by', 'letter_sent_method',
        'payer_response_at', 'payer_response_text', 'payer_response_outcome',
        'parent_denial_id', 'attachments',
    ];

    protected $casts = [
        'denial_date' => 'date', 'appeal_deadline' => 'date', 'appeal_submitted_date' => 'date',
        'denied_amount' => 'decimal:2', 'recovered_amount' => 'decimal:2', 'resolved_at' => 'datetime',
        'triaged_at'         => 'datetime',
        'letter_drafted_at'  => 'datetime',
        'letter_sent_at'     => 'datetime',
        'payer_response_at'  => 'datetime',
        'attachments'        => 'array',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function triagedBy(): BelongsTo { return $this->belongsTo(User::class, 'triaged_by'); }
    public function letterDraftedBy(): BelongsTo { return $this->belongsTo(User::class, 'letter_drafted_by'); }
    public function letterSentBy(): BelongsTo { return $this->belongsTo(User::class, 'letter_sent_by'); }

    /** Parent denial when this row is an escalation (level-2 appeal). */
    public function parentDenial(): BelongsTo { return $this->belongsTo(self::class, 'parent_denial_id'); }
    /** Child denials that escalated FROM this row. */
    public function escalations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_denial_id');
    }
}
