<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientStatement extends Model
{
    use Auditable, BelongsToAgency, SoftDeletes;

    protected $fillable = [
        'agency_id', 'billing_client_id', 'claim_id', 'patient_name', 'patient_email',
        'patient_phone', 'patient_address', 'total_charges', 'insurance_paid', 'adjustments',
        'patient_balance', 'amount_paid', 'status', 'statement_date', 'due_date',
        'last_sent_date', 'times_sent', 'notes', 'created_by',
        // Collections handoff (2026-05-16 migration)
        'handed_off_to_collections_at', 'handed_off_to_collections_by', 'handoff_notes',
        // Snooze + Promise-to-pay (2026-05-16 migration #2)
        'snoozed_until', 'snoozed_by', 'snooze_reason',
        'promised_pay_date', 'promised_pay_amount', 'promised_pay_by',
        'promise_notes', 'promise_broken_at',
    ];

    protected $casts = [
        'total_charges' => 'decimal:2', 'insurance_paid' => 'decimal:2', 'adjustments' => 'decimal:2',
        'patient_balance' => 'decimal:2', 'amount_paid' => 'decimal:2',
        'statement_date' => 'date', 'due_date' => 'date', 'last_sent_date' => 'date',
        'handed_off_to_collections_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'promised_pay_date' => 'date',
        'promised_pay_amount' => 'decimal:2',
        'promise_broken_at' => 'datetime',
    ];

    public function claim(): BelongsTo { return $this->belongsTo(Claim::class); }
    public function billingClient(): BelongsTo { return $this->belongsTo(BillingClient::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
