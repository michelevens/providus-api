<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use App\Models\Traits\ResolvesPayerFromName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use Auditable, BelongsToAgency, ResolvesPayerFromName, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'claim_number',
        // Payer-assigned identifiers. payer_icn is the number shown on
        // remits/EOBs ("Claim #" on Availity). payer_claim_control_number
        // is the 837 REF*F8 used when submitting corrected claims.
        'payer_icn', 'payer_claim_control_number',
        'claim_type', 'status',
        'provider_id', 'provider_name', 'patient_name', 'patient_dob', 'patient_member_id',
        // payer_name is the free-text spelling on the claim. payer_id
        // is the canonical FK; the ResolvesPayerFromName trait stamps
        // it from payer_name on save unless the caller pins it.
        'payer_name', 'payer_id', 'payer_id_number', 'date_of_service', 'date_of_service_end',
        'place_of_service', 'facility_name', 'referring_provider', 'authorization_number',
        'total_charges', 'total_allowed', 'total_paid', 'patient_responsibility',
        'adjustments', 'balance', 'submission_method', 'clearinghouse',
        'submitted_date', 'acknowledged_date', 'adjudicated_date', 'paid_date',
        'check_number', 'denial_reason', 'denial_codes', 'appeal_deadline',
        'notes', 'created_by',
        'original_claim_id', 'corrected_from_denial_id',
        // Stale-claim follow-up workflow. last_status_* mirrors the
        // most recent 276 response so the pending-claims UI can render
        // "checked 2h ago, payer says: in-process" without re-querying.
        // assigned_to / follow_up_due_date / snoozed_until / escalated
        // drive the operator worklist.
        'last_status_check_at', 'last_status_code', 'last_status_category',
        'last_status_response', 'status_inquiry_count',
        'assigned_to', 'follow_up_due_date', 'snoozed_until', 'escalated',
    ];

    protected $casts = [
        'date_of_service' => 'date',
        'date_of_service_end' => 'date',
        'submitted_date' => 'date',
        'acknowledged_date' => 'date',
        'adjudicated_date' => 'date',
        'paid_date' => 'date',
        'appeal_deadline' => 'date',
        'total_charges' => 'decimal:2',
        'total_allowed' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'patient_responsibility' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'balance' => 'decimal:2',
        'last_status_check_at' => 'datetime',
        'last_status_response' => 'array',
        'follow_up_due_date' => 'date',
        'snoozed_until' => 'date',
        'escalated' => 'boolean',
    ];

    public function recalculate(): void
    {
        $this->total_charges = $this->serviceLines()->sum('charges');
        $this->total_paid = $this->serviceLines()->sum('paid_amount');
        $this->adjustments = $this->serviceLines()->sum('adjustment');
        $this->patient_responsibility = $this->serviceLines()->sum('patient_resp');
        $this->balance = $this->total_charges - $this->total_paid - $this->adjustments;
        if ($this->balance <= 0 && $this->total_charges > 0) $this->status = 'paid';
        elseif ($this->total_paid > 0) $this->status = 'partial_paid';
        $this->save();
    }

    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    // Canonical payer FK (populated by ResolvesPayerFromName trait
    // from payer_name on save). Nullable for legacy rows pre-backfill.
    public function payer(): BelongsTo { return $this->belongsTo(Payer::class); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function serviceLines(): HasMany { return $this->hasMany(ClaimServiceLine::class); }
    public function denials(): HasMany { return $this->hasMany(ClaimDenial::class); }
    public function paymentAllocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }
    public function followups(): HasMany { return $this->hasMany(PayerFollowup::class); }
    public function underpaymentFlags(): HasMany { return $this->hasMany(UnderpaymentFlag::class); }
    public function patientStatements(): HasMany { return $this->hasMany(PatientStatement::class); }
    public function statusChecks(): HasMany { return $this->hasMany(ClaimStatusCheck::class)->orderByDesc('checked_at'); }

    // Correction lineage. originalClaim points to the denied claim
    // this one corrects; corrections is the reverse direction so
    // "claim history" UIs can walk forward.
    public function originalClaim(): BelongsTo { return $this->belongsTo(Claim::class, 'original_claim_id'); }
    public function corrections(): HasMany { return $this->hasMany(Claim::class, 'original_claim_id'); }
    public function correctedFromDenial(): BelongsTo { return $this->belongsTo(ClaimDenial::class, 'corrected_from_denial_id'); }
}
