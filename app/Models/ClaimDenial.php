<?php

namespace App\Models;

use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimDenial extends Model
{
    use BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'claim_id', 'billing_client_id', 'denial_category', 'denial_code',
        'denial_reason', 'denied_amount', 'status', 'priority', 'denial_date',
        'appeal_deadline', 'appeal_level', 'appeal_submitted_date', 'recovered_amount',
        'appeal_notes', 'resolution_notes', 'assigned_to', 'created_by', 'resolved_at',
    ];

    protected $casts = [
        'denial_date' => 'date', 'appeal_deadline' => 'date', 'appeal_submitted_date' => 'date',
        'denied_amount' => 'decimal:2', 'recovered_amount' => 'decimal:2', 'resolved_at' => 'datetime',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
